<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use fruiter\AiModeration\Api\Controller\TestConnectionController;
use fruiter\AiModeration\Console\CheckCommand;
use fruiter\AiModeration\Console\ConfigureCommand;
use fruiter\AiModeration\Console\ProcessQueueCommand;
use fruiter\AiModeration\Console\TestCommand;
use fruiter\AiModeration\Listener\DeferredModeration;
use fruiter\AiModeration\Listener\FlagViolation;
use fruiter\AiModeration\Listener\ModerateDiscussion;
use fruiter\AiModeration\Listener\ModeratePost;
use fruiter\AiModeration\Listener\NotifyAuthor;
use fruiter\AiModeration\Notification\ContentModeratedBlueprint;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\Post\Event\Saving as PostSaving;

return [
    (new Extend\Settings())
        ->default('ai-moderation.enabled', true)
        ->default('ai-moderation.api_base_url', 'https://api.openai.com/v1')
        ->default('ai-moderation.api_key', '')
        ->default('ai-moderation.providers', '')
        ->default('ai-moderation.model', 'gpt-4o-mini')
        ->default('ai-moderation.custom_instructions', '')
        ->default('ai-moderation.action', 'hide_and_flag')
        ->default('ai-moderation.check_title', true)
        ->default('ai-moderation.max_chars', 8000)
        ->default('ai-moderation.timeout_seconds', 15)
        ->default('ai-moderation.allow_on_error', true)
        ->default('ai-moderation.json_mode', false)
        ->default('ai-moderation.reject_message', '内容未通过自动审核，请修改后重新提交。如有疑问请联系管理员。')
        ->default('ai-moderation.long_content_chars', 300)
        ->default('ai-moderation.defer_long_content', true)
        ->default('ai-moderation.defer_on_timeout', true)
        ->default('ai-moderation.notify_author', true),

    (new Extend\Event())
        ->listen(PostSaving::class, ModeratePost::class)
        ->listen(DiscussionSaving::class, ModerateDiscussion::class)
        ->listen(Posted::class, FlagViolation::class.'@onPosted')
        ->listen(Revised::class, FlagViolation::class.'@onRevised')
        ->listen(Posted::class, DeferredModeration::class.'@onPosted')
        ->listen(Revised::class, DeferredModeration::class.'@onRevised')
        ->listen(Posted::class, NotifyAuthor::class.'@onPosted')
        ->listen(Revised::class, NotifyAuthor::class.'@onRevised'),

    (new Extend\Notification())
        ->type(ContentModeratedBlueprint::class, PostSerializer::class, ['alert']),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Routes('api'))
        ->get('/ai-moderation/test', 'ai-moderation.test', TestConnectionController::class),

    (new Extend\Console())
        ->command(ConfigureCommand::class)
        ->command(TestCommand::class)
        ->command(CheckCommand::class)
        ->command(ProcessQueueCommand::class),
];
