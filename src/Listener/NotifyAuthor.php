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

use fruiter\AiModeration\Moderation\PendingNotifyRegistry;
use fruiter\AiModeration\Notification\ContentModeratedBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;

/**
 * 同步审核命中违规时，帖子保存完成后通知作者。
 *
 * 注册方式（见 extend.php）：NotifyAuthor::class.'@onPosted' / '@onRevised'
 */
class NotifyAuthor
{
    /** @var NotificationSyncer */
    protected $notifications;

    public function __construct(NotificationSyncer $notifications)
    {
        $this->notifications = $notifications;
    }

    public function onPosted(Posted $event): void
    {
        $this->handle($event->post);
    }

    public function onRevised(Revised $event): void
    {
        $this->handle($event->post);
    }

    /**
     * @param mixed $post
     */
    protected function handle($post): void
    {
        $data = PendingNotifyRegistry::consume($post);

        if ($data === null || ! $post->user) {
            return;
        }

        try {
            $this->notifications->sync(
                new ContentModeratedBlueprint($post, $data['category'], $data['reason']),
                [$post->user]
            );
        } catch (\Throwable $e) {
            // 通知失败不影响发帖流程
        }
    }
}
