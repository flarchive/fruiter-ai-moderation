<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ai_moderation_queue', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('post_id');
    $table->string('status', 20)->default('pending');
    $table->text('error')->nullable();
    $table->dateTime('created_at');
    $table->dateTime('processed_at')->nullable();

    $table->index('status');
    $table->index('post_id');
});
