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

use fruiter\AiModeration\Moderation\FlagCreator;
use fruiter\AiModeration\Moderation\PendingFlagRegistry;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;

/**
 * 帖子保存完成后创建 AI 审核标记（此时帖子已有 id）。
 *
 * 注册方式（见 extend.php）：FlagViolation::class.'@onPosted' / '@onRevised'
 */
class FlagViolation
{
    public function onPosted(Posted $event): void
    {
        $this->createFlagIfPending($event->post);
    }

    public function onRevised(Revised $event): void
    {
        $this->createFlagIfPending($event->post);
    }

    /**
     * @param mixed $post
     */
    protected function createFlagIfPending($post): void
    {
        $decision = PendingFlagRegistry::consume($post);

        if ($decision === null) {
            return;
        }

        FlagCreator::create($post, $decision);
    }
}
