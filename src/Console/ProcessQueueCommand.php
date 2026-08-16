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

use fruiter\AiModeration\Moderation\QueueProcessor;
use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * php flarum ai-moderation:process [--limit=N] —— 处理延迟审核队列。
 * 建议配合 cron 每分钟执行：* * * * * php /path/to/flarum ai-moderation:process
 */
class ProcessQueueCommand extends AbstractCommand
{
    /** @var QueueProcessor */
    protected $processor;

    public function __construct(QueueProcessor $processor)
    {
        $this->processor = $processor;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ai-moderation:process')
            ->setDescription('处理延迟审核队列（长文本 / 超时内容的后置审核）')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, '单次处理条数上限（默认 10）', 10);
    }

    protected function fire()
    {
        $limit = max(1, (int) ($this->input->getOption('limit') ?: 10));

        $count = $this->processor->process($limit);

        $this->info("延迟审核队列处理完成，共处理 {$count} 条。");

        return 0;
    }
}
