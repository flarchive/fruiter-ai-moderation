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
 * AI 审核结果（值对象）。
 */
class Decision
{
    /** @var bool */
    protected $compliant;

    /** @var string|null */
    protected $category;

    /** @var string|null */
    protected $reason;

    /** @var string */
    protected $raw;

    public function __construct(bool $compliant, ?string $category, ?string $reason, string $raw)
    {
        $this->compliant = $compliant;
        $this->category = $category;
        $this->reason = $reason;
        $this->raw = $raw;
    }

    public function isCompliant(): bool
    {
        return $this->compliant;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * 从 AI 的文本响应中解析出结构化结果。
     *
     * 要求模型返回形如 {"compliant": true/false, "category": "...", "reason": "..."} 的 JSON。
     *
     * @throws \RuntimeException 无法解析时抛出
     */
    public static function parse(string $raw): self
    {
        $decoded = static::extractJson($raw);

        if (! is_array($decoded) || ! array_key_exists('compliant', $decoded)) {
            throw new \RuntimeException('无法解析 AI 审核结果: '.mb_substr($raw, 0, 200));
        }

        $compliant = filter_var($decoded['compliant'], FILTER_VALIDATE_BOOLEAN);
        $category = isset($decoded['category']) && is_string($decoded['category']) && $decoded['category'] !== ''
            ? $decoded['category']
            : null;
        $reason = isset($decoded['reason']) && is_string($decoded['reason']) ? $decoded['reason'] : null;

        return new static($compliant, $category, $reason, $raw);
    }

    /**
     * 从响应文本中尽力提取 JSON 对象。
     */
    protected static function extractJson(string $raw): ?array
    {
        $text = trim($raw);

        // 去掉可能的 ```json ... ``` 代码块围栏
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // 宽松模式：截取第一个 { 到最后一个 } 之间的内容再解析
        $first = strpos($text, '{');
        $last = strrpos($text, '}');

        if ($first !== false && $last !== false && $last > $first) {
            $decoded = json_decode(substr($text, $first, $last - $first + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return array{compliant: bool, category: string|null, reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'compliant' => $this->compliant,
            'category' => $this->category,
            'reason' => $this->reason,
        ];
    }
}
