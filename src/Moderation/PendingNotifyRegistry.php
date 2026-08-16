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

/**
 * 请求内暂存"待通知作者"的违规信息。
 *
 * 帖子在 Post\Event\Saving 阶段尚无 id，通知需要帖子保存完成后
 * （Posted / Revised 事件）再发送，因此先把判定结果暂存于此。
 */
class PendingNotifyRegistry
{
    /** @var array<int, array{category: string|null, reason: string|null}> */
    protected static $pending = [];

    /**
     * @param mixed $post
     */
    public static function remember($post, ?string $category, ?string $reason): void
    {
        static::$pending[spl_object_id($post)] = [
            'category' => $category,
            'reason' => $reason,
        ];
    }

    /**
     * @param mixed $post
     */
    public static function consume($post): ?array
    {
        $id = spl_object_id($post);

        if (! isset(static::$pending[$id])) {
            return null;
        }

        $data = static::$pending[$id];
        unset(static::$pending[$id]);

        return $data;
    }
}
