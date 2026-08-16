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
 * 请求内暂存"需要延迟审核"的帖子。
 *
 * 长文本（或审核超时）的内容先允许发布，帖子保存后（Posted / Revised 事件）
 * 由 DeferredModeration 监听器加入延迟审核队列。
 *
 * 静态成员仅在当前 PHP 请求内有效，请求结束自动释放。
 */
class DeferredRegistry
{
    /** @var array<int, bool> */
    protected static $pending = [];

    /**
     * @param mixed $post
     */
    public static function remember($post): void
    {
        static::$pending[spl_object_id($post)] = true;
    }

    /**
     * @param mixed $post
     */
    public static function consume($post): bool
    {
        $id = spl_object_id($post);

        if (! isset(static::$pending[$id])) {
            return false;
        }

        unset(static::$pending[$id]);

        return true;
    }
}
