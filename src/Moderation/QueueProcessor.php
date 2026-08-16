<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Moderation;

use Carbon\Carbon;
use fruiter\AiModeration\Model\QueueItem;
use fruiter\AiModeration\Notification\ContentModeratedBlueprint;
use fruiter\AiModeration\Support\Log;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Hidden;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * 延迟审核队列：长文本 / 审核超时的内容先发布，再异步（或定时）审核。
 *
 * 触发方式：
 * 1. 后台处理（默认）：帖子保存后注册 shutdown 函数，响应返回后立即处理少量条目；
 * 2. 定时任务：php flarum ai-moderation:process （建议 cron 每分钟执行，兜底）。
 */
class QueueProcessor
{
    /** @var Moderator */
    protected $moderator;

    /** @var SettingsRepositoryInterface */
    protected $settings;

    /** @var NotificationSyncer */
    protected $notifications;

    /** @var Dispatcher */
    protected $events;

    public function __construct(
        Moderator $moderator,
        SettingsRepositoryInterface $settings,
        NotificationSyncer $notifications,
        Dispatcher $events
    ) {
        $this->moderator = $moderator;
        $this->settings = $settings;
        $this->notifications = $notifications;
        $this->events = $events;
    }

    /**
     * 将帖子加入延迟审核队列（已存在则不重复加入）。
     *
     * @param mixed $post 已保存的帖子（必须有 id）
     */
    public function enqueue($post): void
    {
        try {
            $item = QueueItem::firstOrNew(['post_id' => $post->id]);

            if (! $item->exists) {
                $item->status = 'pending';
                $item->created_at = Carbon::now();
                $item->save();
            }
        } catch (\Throwable $e) {
            Log::error('加入延迟审核队列失败: '.$e->getMessage());
        }
    }

    /**
     * 在响应发送之后（shutdown 阶段）后台处理最多 $limit 条待审核项。
     *
     * 适用于 PHP-FPM / PHP 内置服务器；若进程被提前终止，
     * 未处理条目会保留在队列中，由 ai-moderation:process（cron）兜底。
     */
    public function processInBackground(int $limit = 1): void
    {
        if ($limit <= 0) {
            return;
        }

        @ignore_user_abort(true);

        register_shutdown_function(function () use ($limit) {
            try {
                $this->process($limit);
            } catch (\Throwable $e) {
                Log::error('后台延迟审核失败: '.$e->getMessage());
            }
        });
    }

    /**
     * 处理最多 $limit 条待审核项（pending 或之前 failed 的，失败可重试），返回成功处理条数。
     *
     * @return int
     */
    public function process(int $limit = 10): int
    {
        $items = QueueItem::whereIn('status', ['pending', 'failed'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($items as $item) {
            try {
                $this->processItem($item);
                $item->status = 'done';
                $item->processed_at = Carbon::now();
                $item->save();
                $processed++;
            } catch (\Throwable $e) {
                $item->status = 'failed';
                $item->error = mb_substr($e->getMessage(), 0, 500);
                $item->processed_at = Carbon::now();
                $item->save();
                Log::error('延迟审核处理失败: '.$e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * 审核单条队列项：违规则隐藏 / 标记 / 通知作者。
     */
    protected function processItem(QueueItem $item): void
    {
        /** @var CommentPost|null $post */
        $post = Post::find($item->post_id);

        if (! $post || ! $post instanceof CommentPost) {
            return; // 帖子已删除等情况：直接标记完成
        }

        $content = (string) $post->content;
        if (trim($content) === '') {
            return;
        }

        // 首帖附带讨论标题
        $title = null;
        try {
            $discussion = $post->discussion;

            if ($discussion && $post->id === $discussion->first_post_id) {
                $title = (string) $discussion->title;
            }
        } catch (\Throwable $e) {
            // 忽略：降级为只检查内容
        }

        $text = $content;
        if ($title !== null && $title !== '' && (bool) $this->settings->get('ai-moderation.check_title', true)) {
            $text = "【标题】{$title}\n【内容】{$content}";
        }
        $text = mb_substr($text, 0, max(1, (int) $this->settings->get('ai-moderation.max_chars', 8000)));

        $decision = $this->moderator->check($text);

        if ($decision->isCompliant()) {
            return;
        }

        $action = (string) $this->settings->get('ai-moderation.action', 'hide_and_flag');
        // 延迟审核时内容已发布，'reject' 无法撤回，降级为隐藏 + 标记 + 通知
        $doHide = in_array($action, ['hide', 'hide_and_flag', 'reject'], true);
        $doFlag = in_array($action, ['flag', 'hide_and_flag', 'reject'], true);

        if ($doHide) {
            $post->hidden_at = Carbon::now();
            $post->hidden_user_id = null;
            $post->save();

            $this->events->dispatch(new Hidden($post));
            Log::info("延迟审核：帖子 {$post->id} 已隐藏");
        }

        if ($doFlag) {
            FlagCreator::create($post, $decision);
        }

        $this->notifyAuthor($post, $decision);
    }

    /**
     * @param mixed $post
     */
    protected function notifyAuthor($post, Decision $decision): void
    {
        if (! (bool) $this->settings->get('ai-moderation.notify_author', true) || ! $post->user) {
            return;
        }

        try {
            $this->notifications->sync(
                new ContentModeratedBlueprint($post, $decision->getCategory(), $decision->getReason()),
                [$post->user]
            );
        } catch (\Throwable $e) {
            Log::error('发送作者通知失败: '.$e->getMessage());
        }
    }
}
