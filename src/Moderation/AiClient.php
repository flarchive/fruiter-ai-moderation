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
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client as GuzzleClient;

/**
 * OpenAI 兼容协议的 Chat Completions 客户端。
 *
 * 适用于：OpenAI、DeepSeek、Moonshot（月之暗面）、智谱 GLM、通义千问、
 * Ollama（OpenAI 兼容端口）、LM Studio、vLLM、OneAPI / NewAPI 等
 * 一切提供 /chat/completions 接口的服务。
 *
 * 安全：api_key / api_base_url / model 支持通过 config.php 覆盖（优先于数据库设置），
 * 生产环境建议用 config.php 保存密钥，避免密钥进入数据库。
 */
class AiClient
{
    /** @var SettingsRepositoryInterface */
    protected $settings;

    /** @var Config */
    protected $config;

    /** @var GuzzleClient */
    protected $http;

    public function __construct(SettingsRepositoryInterface $settings, Config $config)
    {
        $this->settings = $settings;
        $this->config = $config;
        $this->http = new GuzzleClient();
    }

    /**
     * 发送对话请求，返回模型回复的文本内容。
     *
     * @param array $messages [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
     * @return string
     *
     * @throws \RuntimeException AI 服务不可用或返回错误
     */
    public function chat(array $messages): string
    {
        $baseUrl = $this->configValue('api_base_url') ?? (string) $this->settings->get('ai-moderation.api_base_url', 'https://api.openai.com/v1');
        $apiKey = $this->configValue('api_key') ?? (string) $this->settings->get('ai-moderation.api_key', '');
        $model = $this->configValue('model') ?? (string) $this->settings->get('ai-moderation.model', 'gpt-4o-mini');
        $timeout = (float) $this->settings->get('ai-moderation.timeout_seconds', 15);
        $jsonMode = (bool) $this->settings->get('ai-moderation.json_mode', false);

        $url = rtrim($baseUrl, '/').'/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0,
            'max_tokens' => 2048,
            'stream' => false,
        ];

        // 严格 JSON 模式：仅当服务商支持 OpenAI 的 response_format 时开启
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        try {
            $response = $this->http->post($url, [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => $timeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('请求 AI 服务失败: '.$e->getMessage());

            throw new \RuntimeException('无法连接 AI 审核服务: '.$e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($statusCode >= 400) {
            $detail = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : mb_substr($body, 0, 500);
            Log::error("AI 服务返回错误 (HTTP {$statusCode}): {$detail}");

            throw new \RuntimeException("AI 审核服务返回错误 (HTTP {$statusCode}): ".mb_substr($detail, 0, 500));
        }

        if (! is_array($decoded) || ! isset($decoded['choices'][0]['message']['content'])) {
            throw new \RuntimeException('AI 审核服务返回了无法识别的响应');
        }

        return (string) $decoded['choices'][0]['message']['content'];
    }

    /**
     * 读取 config.php 中 ai-moderation 组的配置覆盖项（优先级高于数据库设置）。
     *
     * @param string $key api_key | api_base_url | model
     */
    protected function configValue(string $key): ?string
    {
        $group = $this->config['ai-moderation'] ?? null;

        if (is_array($group) && isset($group[$key])) {
            return (string) $group[$key];
        }

        return null;
    }
}
