<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Support;

/**
 * 轻量日志工具。
 *
 * 优先写入 Flarum 的日志服务（若容器注册了 'log' 绑定），
 * 否则回退到 PHP 的 error_log（在 Flarum 中通常也会进入 storage/logs/flarum.log）。
 *
 * 安全说明：日志中不会记录 API 密钥。
 */
class Log
{
    public static function write(string $level, string $message): void
    {
        $line = "[ai-moderation] [{$level}] {$message}";

        if (function_exists('resolve')) {
            try {
                $logger = resolve('log');

                if (is_object($logger) && method_exists($logger, $level)) {
                    $logger->{$level}($line);

                    return;
                }
            } catch (\Throwable $e) {
                // 忽略：回退到 error_log
            }
        }

        @error_log($line);
    }

    public static function info(string $message): void
    {
        static::write('info', $message);
    }

    public static function warning(string $message): void
    {
        static::write('warning', $message);
    }

    public static function error(string $message): void
    {
        static::write('error', $message);
    }
}
