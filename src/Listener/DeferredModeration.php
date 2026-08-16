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

use fruiter\AiModeration\Moderation\DeferredRegistry;
use fruiter\AiModeration\Moderation\QueueProcessor;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;

/**
 * 帖子保存完成后：长文本 / 超时内容进入延迟审核队列，
 * 并尝试在响应返回后后台立即处理。
 *
 * 注册方式（见 extend.php）：DeferredModeration::class.'@onPosted' / '@onRevised'
 */
class DeferredModeration
{
    /** @var QueueProcessor */
    protected $processor;

    public function __construct(QueueProcessor $processor)
    {
        $this->processor = $processor;
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
        if (! DeferredRegistry::consume($post)) {
            return;
        }

        $this->processor->enqueue($post);

        // 响应返回后在后台处理（FPM / php -S 均可）；失败时由 cron 兜底
        $this->processor->processInBackground(1);
    }
}
