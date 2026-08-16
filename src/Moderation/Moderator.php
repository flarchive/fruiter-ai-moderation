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

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * 审核协调器：构建提示词、调用 AI、解析结果。
 */
class Moderator
{
    /**
     * 内置默认审核提示词（可在后台通过"自定义指令"追加规则）。
     *
     * 设计要点：
     * - 明确列出违规类别；
     * - 要求结合上下文判断，避免把"新闻报道/科普/讨论/举报"语境误判为违规；
     * - 要求识别谐音字、变体词、隐晦暗示等规避手段；
     * - 无明确违规证据时判定为合规，降低误杀；
     * - 严格输出 JSON，方便程序解析。
     */
    const DEFAULT_SYSTEM_PROMPT = <<<PROMPT
你是一个中文网络社区内容安全审核助手。用户会给你一段待发布的内容，你需要判断它是否合规。

【判定为"不合规"的内容类型】包括（但不限于）：
1. 违反法律法规：政治敏感、煽动颠覆国家政权、分裂国家、攻击国家领导人、传播谣言、宣扬恐怖主义或极端主义；
2. 色情淫秽：露骨色情、低俗擦边、色情交易、淫秽图文；
3. 暴力血腥：宣扬暴力、血腥恐怖、教唆自残自杀、煽动伤害他人；
4. 违法活动：赌博、毒品、诈骗、传销、刷单、买卖违禁品、网络攻击工具；
5. 侮辱攻击：人身攻击、侮辱诽谤、歧视言论、网络暴力、人肉搜索、泄露他人隐私；
6. 垃圾信息：恶意广告引流、刷屏、垃圾链接、外链推广；
7. 其他违背社会公序良俗的内容。

【判定要求】
1. 结合上下文判断意图：如果内容只是在新闻报道、科普教育、学习讨论、举报揭发、批判警示等语境下提及敏感词（如"赌博""诈骗""毒品"），且没有宣扬、传播、教唆、引导违规行为，应判定为合规；
2. 注意识别谐音字、变体词、拆字、拼音、隐晦暗示等规避手段，不要被其绕过；
3. 不要仅因为内容包含"赌博""诈骗""色情"等字眼就判定违规，关键是看它是否在宣扬或引导这些行为；
4. 当内容没有明确违规证据时，应判定为合规，避免误杀正常讨论；
5. 对于语气强烈但仍在正常讨论范围内（如吐槽、批评、争论）的内容，判定为合规。

【输出格式】
只输出一个 JSON 对象，不要输出任何其他文字。格式严格如下：
{"compliant": true, "category": null, "reason": ""}
或者（违规时）：
{"compliant": false, "category": "违规类别", "reason": "一句话说明违规原因"}
PROMPT;

    /** @var AiClient */
    protected $client;

    /** @var SettingsRepositoryInterface */
    protected $settings;

    public function __construct(AiClient $client, SettingsRepositoryInterface $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    /**
     * 检查一段文本，返回审核决定。
     *
     * @throws \RuntimeException AI 服务不可用或响应无法解析
     */
    public function check(string $text): Decision
    {
        $raw = $this->client->chat($this->buildMessages($text));

        return Decision::parse($raw);
    }

    /**
     * 测试连接：向 AI 发送一段明显合规的样例文本，验证请求、鉴权与解析链路。
     *
     * @return array{raw: string, decision: array}
     *
     * @throws \RuntimeException
     */
    public function testConnection(): array
    {
        $sample = '这是一条正常的社区技术讨论内容，讨论 PHP 与数据库的优化，没有任何违规。';
        $raw = $this->client->chat($this->buildMessages($sample));
        $decision = Decision::parse($raw);

        return [
            'raw' => $raw,
            'decision' => $decision->toArray(),
        ];
    }

    /**
     * 构建 Chat Completions 消息数组。
     *
     * @param string $text
     * @return array
     */
    protected function buildMessages(string $text): array
    {
        $system = static::DEFAULT_SYSTEM_PROMPT;

        $custom = (string) $this->settings->get('ai-moderation.custom_instructions', '');
        if ($custom !== '') {
            $system .= "\n\n补充审核要求：\n".$custom;
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $text],
        ];
    }
}
