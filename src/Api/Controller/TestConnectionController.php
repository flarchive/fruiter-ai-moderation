<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Api\Controller;

use fruiter\AiModeration\Moderation\Moderator;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/ai-moderation/test —— 管理员测试 AI 连接（后台"测试连接"按钮调用）。
 *
 * 安全：仅管理员可访问（未认证或非管理员一律 403），
 * 避免匿名用户滥用站点配置的 AI 密钥触发计费请求。
 */
class TestConnectionController implements RequestHandlerInterface
{
    /** @var Moderator */
    protected $moderator;

    public function __construct(Moderator $moderator)
    {
        $this->moderator = $moderator;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $request->getAttribute('actor');

        if ($actor === null || ! $actor->isAdmin()) {
            return new JsonResponse(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $result = $this->moderator->testConnection();

            return new JsonResponse(['ok' => true, 'data' => $result], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
