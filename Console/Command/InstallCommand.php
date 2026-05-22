<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Console\Command;

use Gtstudio\MagentoBoost\Install\AgentDetector;
use Gtstudio\MagentoBoost\Install\Agents\AgentInterface;
use Gtstudio\MagentoBoost\Install\Agents\ClaudeCodeAgent;
use Gtstudio\MagentoBoost\Install\Agents\CodexAgent;
use Gtstudio\MagentoBoost\Install\Agents\CursorAgent;
use Gtstudio\MagentoBoost\Install\Agents\GeminiAgent;
use Gtstudio\MagentoBoost\Install\Agents\JunieAgent;
use Gtstudio\MagentoBoost\Install\Agents\VsCodeAgent;
use Gtstudio\MagentoBoost\Install\BoostConfig;
use Gtstudio\MagentoBoost\Install\MagerunDetector;
use Gtstudio\MagentoBoost\Install\ModuleInventory;
use Gtstudio\MagentoBoost\Install\ThemeDetector;
use Gtstudio\MagentoBoost\Install\Writers\GuidelineWriter;
use Gtstudio\MagentoBoost\Install\Writers\SkillWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallCommand extends Command
{
    private const AVAILABLE_AGENTS = [
        'claude-code' => ClaudeCodeAgent::class,
        'codex'       => CodexAgent::class,
        'cursor'      => CursorAgent::class,
        'vscode'      => VsCodeAgent::class,
        'gemini'      => GeminiAgent::class,
        'junie'       => JunieAgent::class,
    ];

    private const GUIDELINE_PACKS = ['core', 'frontend', 'commerce-cloud'];

    private const SKILL_PACKS = ['module-scaffolding', 'plugin-observer-recipes', 'db-schema-changes'];

    private const MCP_PRESETS = [
        'read-only'  => 'Safe: exposes only sys, config:* list, dev:* list, indexer status, cache list',
        'dev-safe'   => 'Balanced: read-only + cache flush, indexer reindex, setup:upgrade',
        'everything' => 'Full: all magerun commands (caution: includes db:query, customer:delete, etc.)',
    ];

    protected function configure(): void
    {
        $this->setName('boost:install')
            ->setDescription('Install and configure magento-boost AI agent tooling');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot  = $this->getProjectRoot();
        $resourcesDir = __DIR__ . '/../../Resources';

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $output->writeln('');
        $output->writeln('<info>  ███╗   ███╗ █████╗  ██████╗ ███████╗███╗   ██╗████████╗ ██████╗     ██████╗  ██████╗  ██████╗ ███████╗████████╗</info>');
        $output->writeln('<info>  ████╗ ████║██╔══██╗██╔════╝ ██╔════╝████╗  ██║╚══██╔══╝██╔═══██╗    ██╔══██╗██╔═══██╗██╔═══██╗██╔════╝╚══██╔══╝</info>');
        $output->writeln('<info>  ██╔████╔██║███████║██║  ███╗█████╗  ██╔██╗ ██║   ██║   ██║   ██║    ██████╔╝██║   ██║██║   ██║███████╗   ██║   </info>');
        $output->writeln('<info>  ██║╚██╔╝██║██╔══██║██║   ██║██╔══╝  ██║╚██╗██║   ██║   ██║   ██║    ██╔══██╗██║   ██║██║   ██║╚════██║   ██║   </info>');
        $output->writeln('<info>  ██║ ╚═╝ ██║██║  ██║╚██████╔╝███████╗██║ ╚████║   ██║   ╚██████╔╝    ██████╔╝╚██████╔╝╚██████╔╝███████║   ██║   </info>');
        $output->writeln('<info>  ╚═╝     ╚═╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝  ╚═══╝   ╚═╝    ╚═════╝     ╚═════╝  ╚═════╝  ╚═════╝ ╚══════╝   ╚═╝   </info>');
        $output->writeln('');
        $output->writeln('  <comment>AI-powered development tools for Magento 2</comment>');
        $output->writeln('');

        // ── Step 1: detect existing configs ──────────────────────────────
        $agentDetector = new AgentDetector($projectRoot);
        $detectedFiles  = $agentDetector->detectedFiles();
        $detectedAgents = $agentDetector->detected();

        if ($detectedFiles) {
            $output->writeln('<comment>Detected existing AI config files:</comment> ' . implode(', ', $detectedFiles));
        }
        if ($detectedAgents) {
            $output->writeln('<comment>Detected configured agents:</comment> ' . implode(', ', $detectedAgents));
        }

        // ── Step 2: detect theme ──────────────────────────────────────────
        $themeInfo = (new ThemeDetector($projectRoot))->detect();
        $themeMsg  = '<info>✓</info> Detected theme: <info>' . $themeInfo->label() . '</info>';
        if ($themeInfo->customThemeName) {
            $themeMsg .= ' (<comment>' . $themeInfo->customThemeName . '</comment>)';
        }
        $output->writeln($themeMsg);
        $output->writeln('');

        // ── Step 3: detect magerun ────────────────────────────────────────
        $magerunDetector = new MagerunDetector($projectRoot);
        $magerunBin      = $magerunDetector->detect();
        $magerunVersion  = null;

        if ($magerunBin) {
            $magerunVersion = $magerunDetector->version($magerunBin);
            if ($magerunVersion && $magerunDetector->meetsMinimum($magerunVersion)) {
                $output->writeln('<info>✓</info> Found n98-magerun2 <info>' . $magerunVersion . '</info> at <comment>' . $magerunBin . '</comment>');
            } elseif ($magerunVersion) {
                $output->writeln('<comment>⚠</comment>  Found n98-magerun2 <comment>' . $magerunVersion . '</comment> at <comment>' . $magerunBin . '</comment>');
                $output->writeln('   MCP server requires <info>9.4.0+</info> for stable tool names. Consider upgrading.');
            } else {
                $output->writeln('<comment>⚠</comment>  Found magerun binary but could not determine version: <comment>' . $magerunBin . '</comment>');
            }
        } else {
            $output->writeln('<comment>⚠</comment>  n98-magerun2 not found. MCP server will not be configured.');
            $output->writeln('   Install via: <info>composer require --dev n98/magerun2-dist:^9.4</info>');
        }
        $output->writeln('');

        // ── Step 4: choose AI agents ──────────────────────────────────────
        $agentLabels = array_map(
            fn(string $class) => (new $class())->label(),
            self::AVAILABLE_AGENTS
        );
        $agentKeys = array_keys(self::AVAILABLE_AGENTS);

        $q = new ChoiceQuestion(
            '<question>Which AI agents do you want to configure? (comma-separated, e.g. 0,2)</question>',
            array_values($agentLabels),
            implode(',', array_keys(array_intersect($agentKeys, $detectedAgents)))
        );
        $q->setMultiselect(true);
        $chosenLabels = $helper->ask($input, $output, $q);

        $chosenAgents = [];
        foreach ($chosenLabels as $label) {
            $key = array_search($label, $agentLabels, true);
            if ($key !== false) {
                $class          = self::AVAILABLE_AGENTS[$key];
                $chosenAgents[] = new $class();
            }
        }

        if (empty($chosenAgents)) {
            $output->writeln('<comment>No agents selected. Exiting.</comment>');
            return self::SUCCESS;
        }

        // ── Step 5: choose guideline packs ────────────────────────────────
        $q = new ChoiceQuestion(
            '<question>Which guideline packs to install? (comma-separated)</question>',
            self::GUIDELINE_PACKS,
            '0'
        );
        $q->setMultiselect(true);
        $chosenGuidelines = $helper->ask($input, $output, $q);

        // ── Step 6: choose skill packs ────────────────────────────────────
        $q = new ChoiceQuestion(
            '<question>Which skill packs to install? (comma-separated, or skip)</question>',
            array_merge(['none'], self::SKILL_PACKS),
            '0'
        );
        $q->setMultiselect(true);
        $chosenSkillsRaw = $helper->ask($input, $output, $q);
        $chosenSkills    = array_values(array_filter($chosenSkillsRaw, fn($s) => $s !== 'none'));

        // ── Step 7: MCP registration ──────────────────────────────────────
        $registerMcp = false;
        $mcpPreset   = 'dev-safe';

        if ($magerunBin) {
            $q           = new ConfirmationQuestion('<question>Register MCP server for selected agents? [Y/n]</question> ', true);
            $registerMcp = $helper->ask($input, $output, $q);

            if ($registerMcp) {
                $presetChoices = [];
                foreach (self::MCP_PRESETS as $key => $desc) {
                    $presetChoices[] = $key . ' — ' . $desc;
                }
                $pq        = new ChoiceQuestion(
                    '<question>Choose a magerun tool allowlist preset:</question>',
                    $presetChoices,
                    '1'
                );
                $presetRaw = $helper->ask($input, $output, $pq);
                $mcpPreset = explode(' —', $presetRaw)[0];
            }
        }

        // ── Step 8: apply ─────────────────────────────────────────────────
        $output->writeln('');
        $output->writeln('<info>Installing magento-boost…</info>');

        $guidelineContent = $this->composeGuidelines($resourcesDir, $chosenGuidelines, $themeInfo->guidelineFile());
        $guidelineContent .= $this->composeModuleInventory($projectRoot);

        foreach ($chosenAgents as $agent) {
            /** @var AgentInterface $agent */
            $output->write('  <info>' . $agent->label() . '</info>');

            if ($registerMcp && $magerunBin) {
                $agent->registerMcp($projectRoot, $magerunBin);
                $output->write(' — MCP registered');
            }

            $agent->writeGuidelines($projectRoot, $guidelineContent);
            $output->writeln(' — guidelines written');
        }

        if ($chosenGuidelines) {
            (new GuidelineWriter())->writeShared($projectRoot, $resourcesDir, $chosenGuidelines);
            $output->writeln('  Guidelines copied to <comment>.ai/guidelines/magento-boost/</comment>');
        }

        // Copy theme guideline into .ai/guidelines/magento-boost/theme.md
        $themeGuidelineFile = rtrim($resourcesDir, '/') . '/guidelines/' . $themeInfo->guidelineFile();
        if (file_exists($themeGuidelineFile)) {
            $themeTarget = rtrim($projectRoot, '/') . '/.ai/guidelines/magento-boost';
            if (!is_dir($themeTarget)) {
                mkdir($themeTarget, 0755, true);
            }
            copy($themeGuidelineFile, $themeTarget . '/theme.md');
            $output->writeln('  Theme guidelines (' . $themeInfo->label() . ') copied to <comment>.ai/guidelines/magento-boost/theme.md</comment>');
        }

        if ($chosenSkills) {
            (new SkillWriter())->write($projectRoot, $resourcesDir, $chosenSkills);
            $output->writeln('  Skills copied to <comment>.ai/skills/</comment>');
        }

        // ── Step 9: save boost.json ───────────────────────────────────────
        $config = new BoostConfig($projectRoot);
        $config
            ->set('agents', array_map(fn(AgentInterface $a) => $a->name(), $chosenAgents))
            ->set('guidelines', $chosenGuidelines)
            ->set('skills', $chosenSkills)
            ->set('theme', $themeInfo->type)
            ->set('mcp', [
                'enabled' => $registerMcp,
                'binary'  => $magerunBin ?? null,
                'preset'  => $mcpPreset,
            ])
            ->save();

        $output->writeln('  Configuration saved to <comment>boost.json</comment>');
        $output->writeln('');
        $output->writeln('<info>✓ Done! Run <comment>boost:update</comment> any time to regenerate from boost.json.</info>');

        return self::SUCCESS;
    }

    private function composeGuidelines(string $resourcesDir, array $packs, string $themeGuidelineFile): string
    {
        $parts = [];
        foreach ($packs as $pack) {
            $file = rtrim($resourcesDir, '/') . '/guidelines/' . $pack . '.md';
            if (file_exists($file)) {
                $parts[] = file_get_contents($file);
            }
        }
        // Append theme-specific guidelines
        $themeFile = rtrim($resourcesDir, '/') . '/guidelines/' . $themeGuidelineFile;
        if (file_exists($themeFile)) {
            $parts[] = file_get_contents($themeFile);
        }
        return implode(PHP_EOL . PHP_EOL, $parts);
    }

    private function composeModuleInventory(string $projectRoot): string
    {
        $inventory = new ModuleInventory($projectRoot);
        $summary   = $inventory->summarize();
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
