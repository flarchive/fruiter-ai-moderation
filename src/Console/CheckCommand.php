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
use Symfony\Component\Console\Input\InputArgument;

/**
 * php flarum ai-moderation:check "要检查的文本" —— 手动检查一段文本。
 */
class CheckCommand extends AbstractCommand
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
            ->setName('ai-moderation:check')
            ->setDescription('用 AI 审核服务检查一段文本是否合规')
            ->addArgument('text', InputArgument::REQUIRED, '要检查的文本内容');
    }

    protected function fire()
    {
        $text = (string) $this->input->getArgument('text');

        try {
            $decision = $this->moderator->check($text);

            $this->output->writeln(json_encode($decision->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            if ($decision->isCompliant()) {
                $this->info('判定：合规');

                return 0;
            }

            $this->error('判定：违规'.($decision->getCategory() ? '（'.$decision->getCategory().'）' : ''));

            return 1;
        } catch (\Throwable $e) {
            $this->error('检查失败: '.$e->getMessage());

            return 1;
        }
    }
}
