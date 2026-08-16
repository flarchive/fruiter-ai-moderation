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

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * php flarum ai-moderation:configure --base-url=... --api-key=... --model=... --action=...
 */
class ConfigureCommand extends AbstractCommand
{
    /** @var SettingsRepositoryInterface */
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ai-moderation:configure')
            ->setDescription('配置 AI 内容审核（OpenAI 兼容接口）')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'API 基础地址，如 https://api.openai.com/v1（不含 /chat/completions）')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API 密钥（无需密钥的服务可留空）')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, '模型名称，如 gpt-4o-mini / deepseek-chat / moonshot-v1-8k')
            ->addOption('action', null, InputOption::VALUE_REQUIRED, '违规处理方式: hide_and_flag | hide | flag | reject')
            ->addOption('enabled', null, InputOption::VALUE_NONE, '启用 AI 审核')
            ->addOption('disabled', null, InputOption::VALUE_NONE, '停用 AI 审核');
    }

    protected function fire()
    {
        $map = [
            'base-url' => 'ai-moderation.api_base_url',
            'api-key' => 'ai-moderation.api_key',
            'model' => 'ai-moderation.model',
            'action' => 'ai-moderation.action',
        ];

        $changed = [];

        foreach ($map as $option => $key) {
            $value = $this->input->getOption($option);

            if ($value !== null) {
                $this->settings->set($key, $value);
                $changed[] = $key;
            }
        }

        if ($this->input->getOption('enabled')) {
            $this->settings->set('ai-moderation.enabled', true);
            $changed[] = 'ai-moderation.enabled';
        }

        if ($this->input->getOption('disabled')) {
            $this->settings->set('ai-moderation.enabled', false);
            $changed[] = 'ai-moderation.enabled';
        }

        if (! $changed) {
            $this->info('未提供任何选项，未做修改。可用选项：--base-url / --api-key / --model / --action / --enabled / --disabled');

            return 0;
        }

        $this->info('已更新设置: '.implode(', ', $changed));

        return 0;
    }
}
