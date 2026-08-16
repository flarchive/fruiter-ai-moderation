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
 * 支持配置多个 AI 来源（提供商）做随机负载均衡：
 * - 后台"多 AI 来源（提供商）"填写 JSON 数组，每项含 api_base_url / api_key / model（可选 json_mode）；
 * - 也可通过 config.php 的 ai-moderation.providers 配置（优先级最高）；
 * - 未配置多来源时，使用单来源设置（api_base_url / api_key / model）。
 *
 * 安全：密钥仅管理员可见（不序列化到论坛前端）；生产环境建议用 config.php 保存密钥，
 * 避免密钥进入数据库；日志中不会记录密钥。
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
        $provider = $this->pickProvider();

        $baseUrl = $provider['api_base_url'];
        $apiKey = $provider['api_key'];
        $model = $provider['model'];
        $timeout = (float) $this->settings->get('ai-moderation.timeout_seconds', 15);
        $jsonMode = $provider['json_mode'];

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
     * 随机选取一个 AI 来源（提供商）用于本次请求（多来源负载均衡）。
     *
     * @return array{api_base_url: string, api_key: string, model: string, json_mode: bool}
     */
    protected function pickProvider(): array
    {
        $providers = $this->getProviders();

        return $providers[array_rand($providers)];
    }

    /**
     * 汇总全部可用 AI 来源。
     *
     * 优先级：config.php 的 ai-moderation.providers
     *        > 后台 providers（JSON 数组）
     *        > 单来源设置（api_base_url / api_key / model）
     *
     * @return array<int, array{api_base_url: string, api_key: string, model: string, json_mode: bool}>
     */
    protected function getProviders(): array
    {
        $providers = [];

        // config.php 覆盖
        $group = $this->config['ai-moderation'] ?? null;

        if (is_array($group) && ! empty($group['providers']) && is_array($group['providers'])) {
            foreach ($group['providers'] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $normalized = $this->normalizeProvider($entry);

                if ($normalized !== null) {
                    $providers[] = $normalized;
                }
            }
        }

        // 后台 providers（JSON 数组）
        $json = trim((string) $this->settings->get('ai-moderation.providers', ''));

        if ($json !== '') {
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                foreach ($decoded as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $normalized = $this->normalizeProvider($entry);

                    if ($normalized !== null) {
                        $providers[] = $normalized;
                    }
                }
            } else {
                Log::warning('ai-moderation.providers 不是合法的 JSON 数组，已忽略，回退到单来源设置');
            }
        }

        if (! empty($providers)) {
            return $providers;
        }

        // 兜底：单来源设置
        return [[
            'api_base_url' => $this->configValue('api_base_url') ?? (string) $this->settings->get('ai-moderation.api_base_url', 'https://api.openai.com/v1'),
            'api_key' => $this->configValue('api_key') ?? (string) $this->settings->get('ai-moderation.api_key', ''),
            'model' => $this->configValue('model') ?? (string) $this->settings->get('ai-moderation.model', 'gpt-4o-mini'),
            'json_mode' => (bool) $this->settings->get('ai-moderation.json_mode', false),
        ]];
    }

    /**
     * 规范化单个提供商配置；非法条目（缺少 api_base_url / model，或协议不是 http(s)）返回 null。
     *
     * @param array $entry
     * @return array{api_base_url: string, api_key: string, model: string, json_mode: bool}|null
     */
    protected function normalizeProvider(array $entry): ?array
    {
        $baseUrl = trim((string) ($entry['api_base_url'] ?? $entry['base_url'] ?? ''));
        $model = trim((string) ($entry['model'] ?? ''));
        $apiKey = trim((string) ($entry['api_key'] ?? ''));

        // 只允许 http / https 协议，避免非预期协议
        if ($baseUrl === '' || $model === '' || ! preg_match('#^https?://#i', $baseUrl)) {
            Log::warning('ai-moderation.providers 中存在非法来源条目（缺少 api_base_url/model 或协议不支持），已跳过');

            return null;
        }

        $jsonMode = isset($entry['json_mode'])
            ? (bool) $entry['json_mode']
            : (bool) $this->settings->get('ai-moderation.json_mode', false);

        return [
            'api_base_url' => $baseUrl,
            'api_key' => $apiKey,
            'model' => $model,
            'json_mode' => $jsonMode,
        ];
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
