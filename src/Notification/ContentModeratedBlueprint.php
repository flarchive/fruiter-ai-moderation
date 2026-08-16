<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * "内容未通过审核"作者通知。
 *
 * 类型标识：contentModerated（前台通知组件按此注册）。
 */
class ContentModeratedBlueprint implements BlueprintInterface
{
    /** @var Post */
    public $post;

    /** @var string|null */
    public $category;

    /** @var string|null */
    public $reason;

    public function __construct(Post $post, ?string $category = null, ?string $reason = null)
    {
        $this->post = $post;
        $this->category = $category;
        $this->reason = $reason;
    }

    public function getFromUser(): ?User
    {
        return null; // 系统通知
    }

    public function getSubject()
    {
        return $this->post;
    }

    public function getData()
    {
        return [
            'category' => $this->category,
            'reason' => $this->reason,
        ];
    }

    public static function getType(): string
    {
        return 'contentModerated';
    }

    public static function getSubjectModel(): string
    {
        return Post::class;
    }
}
