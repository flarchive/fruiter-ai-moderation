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

use fruiter\AiModeration\Support\Log;
use Carbon\Carbon;

/**
 * 创建 / 更新 / 清除 flarum/flags 扩展的"AI 审核"标记。
 */
class FlagCreator
{
    const FLAG_TYPE = 'ai_moderation';

    /**
     * 为帖子创建（或更新）AI 审核标记。
     *
     * @param mixed $post 已保存的帖子（必须有 id）
     * @param Decision $decision
     * @param string|null $note 附加说明（如"讨论标题违规："）
     * @return bool 是否成功（flags 扩展未启用时返回 false）
     */
    public static function create($post, Decision $decision, ?string $note = null): bool
    {
        if (! class_exists(\Flarum\Flags\Flag::class)) {
            return false;
        }

        try {
            $flag = \Flarum\Flags\Flag::query()
                ->where('post_id', $post->id)
                ->where('type', static::FLAG_TYPE)
                ->first();

            if (! $flag) {
                $flag = new \Flarum\Flags\Flag();
                $flag->post_id = $post->id;
                $flag->type = static::FLAG_TYPE;
            }

            $flag->user_id = null; // AI 系统标记
            $flag->reason = $decision->getCategory() ?: static::FLAG_TYPE;

            $detail = trim((string) $decision->getReason());
            if ($note !== null && $note !== '') {
                $detail = trim($note.' '.$detail);
            }
            $flag->reason_detail = $detail;
            $flag->created_at = Carbon::now();

            $flag->save();

            return true;
        } catch (\Throwable $e) {
            Log::error('创建 AI 审核标记失败: '.$e->getMessage());

            return false;
        }
    }

    /**
     * 删除帖子的 AI 审核标记（帖子内容改为合规后调用）。
     *
     * @param mixed $post
     */
    public static function clearAiFlags($post): void
    {
        if (! class_exists(\Flarum\Flags\Flag::class) || ! $post->id) {
            return;
        }

        try {
            \Flarum\Flags\Flag::query()
                ->where('post_id', $post->id)
                ->where('type', static::FLAG_TYPE)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('清除 AI 审核标记失败: '.$e->getMessage());
        }
    }
}
