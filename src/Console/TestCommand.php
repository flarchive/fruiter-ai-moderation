<?php

/*
 * This file is part of the "AI Moderation" extension for Flarum.
 *
 * (c) CNFruiter
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace fruiter\AiModeration\Console;

use fruiter\AiModeration\Moderation\Moderator;
use Flarum\Console\AbstractCommand;

/**
 * php flarum ai-moderation:test —— 测试 AI 连接与解析链路。
 */
class TestCommand extends AbstractCommand
{
    /** @var Moderator */
    protected $moderator;

    public function __construct(Moderator $moderator)
    {
        $this->moderator = $moderator;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ai-moderation:test')
            ->setDescription('测试 AI 内容审核服务连接（OpenAI 兼容接口）');
    }

    protected function fire()
    {
        try {
            $result = $this->moderator->testConnection();

            $this->info('连接成功。AI 原始返回：');
            $this->output->writeln($result['raw']);
            $this->info('解析结果：');
            $this->output->writeln(json_encode($result['decision'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return 0;
        } catch (\Throwable $e) {
            $this->error('连接失败: '.$e->getMessage());

            return 1;
        }
    }
}
