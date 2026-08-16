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
use fruiter\AiModeration\Moderation\DeferredRegistry;
use fruiter\AiModeration\Moderation\FlagCreator;
use fruiter\AiModeration\Moderation\Moderator;
use fruiter\AiModeration\Moderation\PendingFlagRegistry;
use fruiter\AiModeration\Moderation\PendingNotifyRegistry;
use fruiter\AiModeration\Support\Log;
use Flarum\Foundation\ValidationException;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;

/**
 * 帖子（含新讨论首帖、回复、编辑）保存前进行 AI 合规审核。
 *
 * 在 Flarum 1.8 中：
 * - 新建讨论：StartDiscussionHandler 先派发 Discussion\Event\Saving（此时首帖尚未创建），
 *   随后通过 PostReply 命令创建首帖，派发 Post\Event\Saving —— 因此首帖的"标题+内容"
 *   会在这里一并检查；
 * - 回复：PostReplyHandler 派发 Post\Event\Saving；
 * - 编辑：EditPostHandler 在派发 Post\Event\Saving 之前已将新内容写入模型。
 */
class ModeratePost
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

        $post = $event->post;

        // 只审核普通评论帖子（跳过系统帖子等类型）
        if (! $post instanceof CommentPost) {
            return;
        }

        $attributes = is_array($event->data) ? Arr::get($event->data, 'attributes', []) : [];
        $attributes = is_array($attributes) ? $attributes : [];

        // 编辑场景：本次操作未修改 content（例如仅隐藏/恢复帖子）则不重复审核
        if ($post->exists && ! array_key_exists('content', $attributes)) {
            return;
        }

        $content = (string) $post->content;
        if (trim($content) === '') {
            return;
        }

        // 判断是否首帖：新讨论的首帖（discussion->first_post_id 尚未设置）或已有讨论的首帖被编辑
        $title = null;
        $isFirstPost = false;

        try {
            $discussion = $post->discussion;

            if ($discussion) {
                $isFirstPost = ! $post->exists
                    ? $discussion->first_post_id === null
                    : $post->id === $discussion->first_post_id;

                if ($isFirstPost) {
                    $title = (string) $discussion->title;
                }
            }
        } catch (\Throwable $e) {
            // 讨论对象加载失败时降级为只检查内容
        }

        $text = $this->buildCheckText($title, $content);
        $text = mb_substr($text, 0, max(1, (int) $this->settings->get('ai-moderation.max_chars', 8000)));

        if (trim($text) === '') {
            return;
        }

        // 长文本：先允许发布，再进入延迟审核队列（避免用户在发布页等待过久）
        if ($this->shouldDefer($text)) {
            DeferredRegistry::remember($post);

            return;
        }

        try {
            $decision = $this->moderator->check($text);
        } catch (\Throwable $e) {
            Log::error('帖子审核调用失败: '.$e->getMessage());

            if (! (bool) $this->settings->get('ai-moderation.allow_on_error', true)) {
                throw new ValidationException([
                    'message' => '内容审核服务暂时不可用，请稍后重试。',
                ]);
            }

            // 失败放行；若开启"超时也延迟审核"，则加入队列稍后补审
            if ((bool) $this->settings->get('ai-moderation.defer_on_timeout', true)) {
                DeferredRegistry::remember($post);
            }

            return;
        }

        if ($decision->isCompliant()) {
            $this->maybeRestore($post);

            return;
        }

        $this->applyAction($post, $decision);
    }

    /**
     * 若帖子曾被 AI 隐藏（hidden_user_id 为空且存在 AI 标记），
     * 编辑为合规内容后自动恢复，并清除旧标记。
     *
     * @param mixed $post
     */
    protected function maybeRestore($post): void
    {
        if (! $post->exists || $post->hidden_at === null || $post->hidden_user_id !== null) {
            return;
        }

        if (! $this->hasAiFlag($post)) {
            return;
        }

        try {
            $post->restore();
            FlagCreator::clearAiFlags($post);
            Log::info("帖子 {$post->id} 已由 AI 审核自动恢复");
        } catch (\Throwable $e) {
            Log::error('自动恢复帖子失败: '.$e->getMessage());
        }
    }

    /**
     * @param mixed $post
     */
    protected function hasAiFlag($post): bool
    {
        if (! class_exists(\Flarum\Flags\Flag::class) || ! $post->id) {
            return false;
        }

        try {
            return \Flarum\Flags\Flag::query()
                ->where('post_id', $post->id)
                ->where('type', FlagCreator::FLAG_TYPE)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function buildCheckText(?string $title, string $content): string
    {
        if ($title !== null && $title !== '' && (bool) $this->settings->get('ai-moderation.check_title', true)) {
            return "【标题】{$title}\n【内容】{$content}";
        }

        return $content;
    }

    /**
     * 是否需要延迟审核（长文本）。
     */
    protected function shouldDefer(string $text): bool
    {
        if (! (bool) $this->settings->get('ai-moderation.defer_long_content', true)) {
            return false;
        }

        $threshold = max(1, (int) $this->settings->get('ai-moderation.long_content_chars', 300));

        return mb_strlen($text) > $threshold;
    }

    /**
     * @param mixed $post
     */
    protected function applyAction($post, Decision $decision): void
    {
        $action = (string) $this->settings->get('ai-moderation.action', 'hide_and_flag');

        if ($action === 'reject') {
            throw $this->buildRejection($decision);
        }

        $doHide = in_array($action, ['hide', 'hide_and_flag'], true);
        $doFlag = in_array($action, ['flag', 'hide_and_flag'], true);

        if ($doHide) {
            $post->hide();
        }

        if ($doFlag) {
            // 帖子尚未保存（尚无 id），先暂存，待 Posted / Revised 事件后创建标记
            PendingFlagRegistry::remember($post, $decision);
        }

        if ((bool) $this->settings->get('ai-moderation.notify_author', true)) {
            // 帖子保存后由 NotifyAuthor 监听器发送通知
            PendingNotifyRegistry::remember($post, $decision->getCategory(), $decision->getReason());
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
