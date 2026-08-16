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
 * 请求内暂存待创建的违规标记。
 *
 * 帖子在 Post\Event\Saving 阶段尚未保存、没有 id，无法写入 flags 表；
 * 因此先在 Saving 阶段把审核决定暂存于此，等 Posted / Revised 事件
 * （帖子已保存、有 id）时再创建标记。
 *
 * 静态成员仅在当前 PHP 请求内有效，请求结束自动释放
 * （长驻进程如 RoadRunner/Swoole 下需注意，本站按常规 PHP-FPM 部署）。
 */
class PendingFlagRegistry
{
    /** @var array<int, Decision> */
    protected static $pending = [];

    /**
     * @param mixed $post
     */
    public static function remember($post, Decision $decision): void
    {
        static::$pending[spl_object_id($post)] = $decision;
    }

    /**
     * 取出并移除该帖子对应的待处理决定；没有则返回 null。
     *
     * @param mixed $post
     */
    public static function consume($post): ?Decision
    {
        $id = spl_object_id($post);

        if (! isset(static::$pending[$id])) {
            return null;
        }

        $decision = static::$pending[$id];
        unset(static::$pending[$id]);

        return $decision;
    }
}
