<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Console\Command;

use Gtstudio\MagentoBoost\Install\Agents\AgentInterface;
use Gtstudio\MagentoBoost\Install\Agents\ClaudeCodeAgent;
use Gtstudio\MagentoBoost\Install\Agents\CodexAgent;
use Gtstudio\MagentoBoost\Install\Agents\CursorAgent;
use Gtstudio\MagentoBoost\Install\Agents\GeminiAgent;
use Gtstudio\MagentoBoost\Install\Agents\JunieAgent;
use Gtstudio\MagentoBoost\Install\Agents\VsCodeAgent;
use Gtstudio\MagentoBoost\Install\BoostConfig;
use Gtstudio\MagentoBoost\Install\Extension\ExtensionLoader;
use Gtstudio\MagentoBoost\Install\GuidelineComposer;
use Gtstudio\MagentoBoost\Install\ModuleInventory;
use Gtstudio\MagentoBoost\Install\SkillComposer;
use Gtstudio\MagentoBoost\Install\ThemeDetector;
use Gtstudio\MagentoBoost\Install\Writers\GuidelineWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    private const AGENT_MAP = [
        'claude-code' => ClaudeCodeAgent::class,
        'codex'       => CodexAgent::class,
        'cursor'      => CursorAgent::class,
        'vscode'      => VsCodeAgent::class,
        'gemini'      => GeminiAgent::class,
        'junie'       => JunieAgent::class,
    ];

    protected function configure(): void
    {
        $this->setName('boost:update')
            ->setDescription('Re-generate magento-boost files from boost.json (non-interactive)');
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot  = $this->getProjectRoot();
        $resourcesDir = __DIR__ . '/../../Resources';

        $config = (new BoostConfig($projectRoot))->load();

        if (!$config->exists()) {
            $output->writeln('<error>boost.json not found. Run boost:install first.</error>');
            return self::FAILURE;
        }

        $agentNames     = $config->get('agents', []);
        $guidelinePacks = $config->get('guidelines', ['core']);
        $skillPacks     = $config->get('skills', []);
        $mcpConfig      = $config->get('mcp', []);
        $registerMcp    = $mcpConfig['enabled'] ?? false;
        $magerunBin     = $mcpConfig['binary'] ?? null;
        $excluded       = $config->get('extensions', [])['excluded'] ?? [];

        // Re-detect theme and extensions — both may change between installs
        $themeInfo        = (new ThemeDetector($projectRoot))->detect();
        $allExtensions    = (new ExtensionLoader())->load($projectRoot);
        $activeExtensions = (new ExtensionLoader())->filter($allExtensions, $excluded);

        $guidelineContent  = (new GuidelineComposer())->compose(
            $resourcesDir,
            $guidelinePacks,
            $themeInfo->guidelineFile(),
            $activeExtensions
        );
        $guidelineContent .= $this->composeModuleInventory($projectRoot);

        foreach ($agentNames as $agentName) {
            $class = self::AGENT_MAP[$agentName] ?? null;
            if ($class === null) {
                $output->writeln('<comment>Unknown agent: ' . $agentName . ' — skipping</comment>');
                continue;
            }
            /** @var AgentInterface $agent */
            $agent = new $class();
            $output->write('  <info>' . $agent->label() . '</info>');

            if ($registerMcp && $magerunBin) {
                $agent->registerMcp($projectRoot, $magerunBin);
                $output->write(' — MCP registered');
            }

            $agent->writeGuidelines($projectRoot, $guidelineContent);
            $output->writeln(' — guidelines written');
        }

        if ($guidelinePacks) {
            (new GuidelineWriter())->writeShared($projectRoot, $resourcesDir, $guidelinePacks);
        }

        (new SkillComposer())->write($projectRoot, $resourcesDir, $skillPacks, $activeExtensions);

        if ($activeExtensions) {
            $names = implode(', ', array_map(fn($ext) => $ext->name, $activeExtensions));
            $output->writeln('  Extensions merged: <info>' . $names . '</info>');
        }

        $output->writeln('<info>✓ magento-boost updated.</info>');
        return self::SUCCESS;
    }

    private function composeModuleInventory(string $projectRoot): string
    {
        $summary = (new ModuleInventory($projectRoot))->summarize();
        if ($summary === '') {
            return '';
        }
        return PHP_EOL . PHP_EOL . "# This project's modules\n\n" . $summary;
    }

    private function getProjectRoot(): string
    {
        $dir = __DIR__;
        while ($dir !== '/') {
            if (file_exists($dir . '/bin/magento')) {
                return $dir;
            }
            $dir = dirname($dir);
        }
        return getcwd();
    }
}
