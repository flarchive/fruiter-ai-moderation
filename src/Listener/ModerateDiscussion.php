<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Listener;

use fruiter\AiModeration\Moderation\Decision;
use fruiter\AiModeration\Moderation\FlagCreator;
use fruiter\AiModeration\Moderation\Moderator;
use fruiter\AiModeration\Support\Log;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\ValidationException;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;

/**
 * 讨论标题审核：仅处理已存在讨论的标题修改（重命名）。
 *
 * 新建讨论的标题会在首帖审核（ModeratePost，标题+内容一起检查）时覆盖，
 * 因此这里不需要重复检查新建场景。
 */
class ModerateDiscussion
{
    /** @var SettingsRepositoryInterface */
    protected $settings;

    /** @var Moderator */
    protected $moderator;

    public function __construct(SettingsRepositoryInterface $settings, Moderator $moderator)
    {
        $this->settings = $settings;
        $this->moderator = $moderator;
    }

    public function handle(Saving $event): void
    {
        if (! (bool) $this->settings->get('ai-moderation.enabled', true)) {
            return;
        }

        $discussion = $event->discussion;

        // 仅审核已存在讨论的标题修改；新建讨论（exists=false）跳过
        if (! $discussion->exists) {
            return;
        }

        $attributes = is_array($event->data) ? Arr::get($event->data, 'attributes', []) : [];
        $attributes = is_array($attributes) ? $attributes : [];

        // 本次编辑未修改 title（例如仅隐藏/恢复讨论）则不重复审核
        if (! array_key_exists('title', $attributes)) {
            return;
        }

        $title = trim((string) $discussion->title);
        if ($title === '') {
            return;
        }

        try {
            $decision = $this->moderator->check($title);
        } catch (\Throwable $e) {
            Log::error('讨论标题审核调用失败: '.$e->getMessage());

            if (! (bool) $this->settings->get('ai-moderation.allow_on_error', true)) {
                throw new ValidationException([
                    'message' => '内容审核服务暂时不可用，请稍后重试。',
                ]);
            }

            return;
        }

        if ($decision->isCompliant()) {
            return;
        }

        $this->applyAction($discussion, $decision);
    }

    /**
     * @param mixed $discussion
     */
    protected function applyAction($discussion, Decision $decision): void
    {
        $action = (string) $this->settings->get('ai-moderation.action', 'hide_and_flag');

        if ($action === 'reject') {
            throw $this->buildRejection($decision);
        }

        $doHide = in_array($action, ['hide', 'hide_and_flag'], true);
        $doFlag = in_array($action, ['flag', 'hide_and_flag'], true);

        if ($doHide) {
            $discussion->hide();
        }

        if ($doFlag) {
            $firstPost = $discussion->firstPost;

            if ($firstPost) {
                FlagCreator::create($firstPost, $decision, '讨论标题违规：');
            }
        }
    }

    protected function buildRejection(Decision $decision): ValidationException
    {
        $message = (string) $this->settings->get(
            'ai-moderation.reject_message',
            '内容未通过自动审核，请修改后重新提交。如有疑问请联系管理员。'
        );

        $detail = trim(($decision->getCategory() ?? '').'：'.($decision->getReason() ?? ''));
        $detail = trim($detail, '：');

        if ($detail !== '') {
            $message .= "\n".$detail;
        }

        return new ValidationException(['message' => $message]);
    }
}
