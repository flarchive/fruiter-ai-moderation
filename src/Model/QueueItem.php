<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Model;

use Flarum\Database\AbstractModel;
use Flarum\Post\Post;

/**
 * 延迟审核队列项。
 *
 * @property int $id
 * @property int $post_id
 * @property string $status  pending | done | failed
 * @property string|null $error
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $processed_at
 */
class QueueItem extends AbstractModel
{
    protected $table = 'ai_moderation_queue';

    public $timestamps = false;

    protected $guarded = [];

    protected $dates = ['created_at', 'processed_at'];

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
