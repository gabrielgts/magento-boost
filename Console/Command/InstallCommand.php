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
use Gtstudio\MagentoBoost\Install\Extension\BoostExtension;
use Gtstudio\MagentoBoost\Install\Extension\ExtensionLoader;
use Gtstudio\MagentoBoost\Install\GuidelineComposer;
use Gtstudio\MagentoBoost\Install\MagerunDetector;
use Gtstudio\MagentoBoost\Install\ModuleInventory;
use Gtstudio\MagentoBoost\Install\SkillComposer;
use Gtstudio\MagentoBoost\Install\ThemeDetector;
use Gtstudio\MagentoBoost\Install\ThemeInfo;
use Gtstudio\MagentoBoost\Install\Writers\GuidelineWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
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

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot  = $this->getProjectRoot();
        $resourcesDir = __DIR__ . '/../../Resources';
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $this->displayBanner($output);

        $detectedAgents = $this->reportDetections($projectRoot, $output);
        $themeInfo      = $this->reportTheme($projectRoot, $output);
        $allExtensions  = $this->reportExtensions($projectRoot, $output);
        $magerunBin     = $this->reportMagerun($projectRoot, $output);

        $output->writeln('');

        $chosenAgents   = $this->askAgents($input, $output, $helper, $detectedAgents);
        if (empty($chosenAgents)) {
            $output->writeln('<comment>No agents selected. Exiting.</comment>');
            return self::SUCCESS;
        }

        $chosenGuidelines   = $this->askGuidelines($input, $output, $helper);
        $chosenSkills       = $this->askSkills($input, $output, $helper);
        $excludedExtensions = $this->askExtensionExclusions($input, $output, $helper, $allExtensions);
        [$registerMcp, $mcpPreset] = $this->askMcpSettings($input, $output, $helper, $magerunBin);

        $activeExtensions = (new ExtensionLoader())->filter($allExtensions, $excludedExtensions);

        $this->applyChanges(
            $output,
            $projectRoot,
            $resourcesDir,
            $chosenAgents,
            $chosenGuidelines,
            $chosenSkills,
            $themeInfo,
            $activeExtensions,
            $registerMcp,
            $magerunBin,
        );

        (new BoostConfig($projectRoot))
            ->set('agents', array_map(fn(AgentInterface $agent) => $agent->name(), $chosenAgents))
            ->set('guidelines', $chosenGuidelines)
            ->set('skills', $chosenSkills)
            ->set('theme', $themeInfo->type)
            ->set('extensions', ['excluded' => $excludedExtensions])
            ->set('mcp', ['enabled' => $registerMcp, 'binary' => $magerunBin, 'preset' => $mcpPreset])
            ->save();

        $output->writeln('  Configuration saved to <comment>boost.json</comment>');
        $output->writeln('');
        $output->writeln('<info>✓ Done! Run <comment>boost:update</comment> any time to regenerate from boost.json.</info>');

        return self::SUCCESS;
    }

    // ── Detection helpers ─────────────────────────────────────────────────────

    private function reportDetections(string $projectRoot, OutputInterface $output): array
    {
        $detector = new AgentDetector($projectRoot);
        $files    = $detector->detectedFiles();
        $agents   = $detector->detected();

        if ($files) {
            $output->writeln('<comment>Detected existing AI config files:</comment> ' . implode(', ', $files));
        }
        if ($agents) {
            $output->writeln('<comment>Detected configured agents:</comment> ' . implode(', ', $agents));
        }
        return $agents;
    }

    private function reportTheme(string $projectRoot, OutputInterface $output): ThemeInfo
    {
        $themeInfo = (new ThemeDetector($projectRoot))->detect();
        $msg       = '<info>✓</info> Detected theme: <info>' . $themeInfo->label() . '</info>';
        if ($themeInfo->customThemeName) {
            $msg .= ' (<comment>' . $themeInfo->customThemeName . '</comment>)';
        }
        $output->writeln($msg);
        return $themeInfo;
    }

    /** @return BoostExtension[] */
    private function reportExtensions(string $projectRoot, OutputInterface $output): array
    {
        $extensions = (new ExtensionLoader())->load($projectRoot);
        if (!$extensions) {
            return [];
        }
        $output->writeln('<info>✓</info> Found <info>' . count($extensions) . '</info> boost extension(s):');
        foreach ($extensions as $ext) {
            $output->writeln(
                '    <comment>' . $ext->name . '</comment>'
                . ' — ' . $ext->guidelineCount() . ' guideline(s)'
                . ', ' . $ext->skillCount() . ' skill(s)'
            );
        }
        return $extensions;
    }

    private function reportMagerun(string $projectRoot, OutputInterface $output): ?string
    {
        $detector = new MagerunDetector($projectRoot);
        $binary   = $detector->detect();

        if ($binary === null) {
            $output->writeln('<comment>⚠</comment>  n98-magerun2 not found. MCP server will not be configured.');
            $output->writeln('   Install via: <info>composer require --dev n98/magerun2-dist:^9.4</info>');
            return null;
        }

        $version = $detector->version($binary);

        if ($version === null) {
            $output->writeln('<comment>⚠</comment>  Found magerun binary but could not determine version: <comment>' . $binary . '</comment>');
            return $binary;
        }

        if ($detector->meetsMinimum($version)) {
            $output->writeln('<info>✓</info> Found n98-magerun2 <info>' . $version . '</info> at <comment>' . $binary . '</comment>');
            return $binary;
        }

        $output->writeln('<comment>⚠</comment>  Found n98-magerun2 <comment>' . $version . '</comment> at <comment>' . $binary . '</comment>');
        $output->writeln('   MCP server requires <info>9.4.0+</info> for stable tool names. Consider upgrading.');
        return $binary;
    }

    // ── Interactive prompt helpers ────────────────────────────────────────────

    /** @return AgentInterface[] */
    private function askAgents(
        InputInterface  $input,
        OutputInterface $output,
        QuestionHelper  $helper,
        array           $detectedAgents
    ): array {
        $labels  = array_map(fn(string $class) => (new $class())->label(), self::AVAILABLE_AGENTS);
        $keys    = array_keys(self::AVAILABLE_AGENTS);
        $default = implode(',', array_keys(array_intersect($keys, $detectedAgents)));

        $question = new ChoiceQuestion(
            '<question>Which AI agents do you want to configure? (comma-separated, e.g. 0,2)</question>',
            array_values($labels),
            $default
        );
        $question->setMultiselect(true);

        $chosen = [];
        foreach ($helper->ask($input, $output, $question) as $label) {
            $key = array_search($label, $labels, true);
            if ($key !== false) {
                $agentClass = self::AVAILABLE_AGENTS[$key];
                $chosen[]   = new $agentClass();
            }
        }
        return $chosen;
    }

    private function askGuidelines(InputInterface $input, OutputInterface $output, QuestionHelper $helper): array
    {
        $question = new ChoiceQuestion(
            '<question>Which guideline packs to install? (comma-separated)</question>',
            self::GUIDELINE_PACKS,
            '0'
        );
        $question->setMultiselect(true);
        return $helper->ask($input, $output, $question);
    }

    private function askSkills(InputInterface $input, OutputInterface $output, QuestionHelper $helper): array
    {
        $question = new ChoiceQuestion(
            '<question>Which skill packs to install? (comma-separated, or skip)</question>',
            array_merge(['none'], self::SKILL_PACKS),
            '0'
        );
        $question->setMultiselect(true);
        $raw = $helper->ask($input, $output, $question);
        return array_values(array_filter($raw, fn(string $skill) => $skill !== 'none'));
    }

    /** @param BoostExtension[] $extensions */
    private function askExtensionExclusions(
        InputInterface  $input,
        OutputInterface $output,
        QuestionHelper  $helper,
        array           $extensions
    ): array {
        if (empty($extensions)) {
            return [];
        }
        $question  = new Question(
            '<question>Exclude any extensions? (comma-separated package names, or Enter to include all)</question> ',
            ''
        );
        $raw = trim((string) $helper->ask($input, $output, $question));
        if ($raw === '') {
            return [];
        }
        return array_map('trim', explode(',', $raw));
    }

    /** @return array{bool, string} [registerMcp, preset] */
    private function askMcpSettings(
        InputInterface  $input,
        OutputInterface $output,
        QuestionHelper  $helper,
        ?string         $magerunBin
    ): array {
        if ($magerunBin === null) {
            return [false, 'dev-safe'];
        }

        $register = $helper->ask($input, $output, new ConfirmationQuestion(
            '<question>Register MCP server for selected agents? [Y/n]</question> ',
            true
        ));

        if (!$register) {
            return [false, 'dev-safe'];
        }

        $choices = [];
        foreach (self::MCP_PRESETS as $key => $desc) {
            $choices[] = $key . ' — ' . $desc;
        }

        $presetQuestion = new ChoiceQuestion('<question>Choose a magerun tool allowlist preset:</question>', $choices, '1');
        $presetRaw      = $helper->ask($input, $output, $presetQuestion);

        return [true, explode(' —', $presetRaw)[0]];
    }

    // ── Apply ─────────────────────────────────────────────────────────────────

    /**
     * @param AgentInterface[] $chosenAgents
     * @param BoostExtension[] $activeExtensions
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    private function applyChanges(
        OutputInterface $output,
        string          $projectRoot,
        string          $resourcesDir,
        array           $chosenAgents,
        array           $chosenGuidelines,
        array           $chosenSkills,
        ThemeInfo       $themeInfo,
        array           $activeExtensions,
        bool            $registerMcp,
        ?string         $magerunBin,
    ): void {
        $output->writeln('');
        $output->writeln('<info>Installing magento-boost…</info>');

        $guidelineContent  = (new GuidelineComposer())->compose(
            $resourcesDir,
            $chosenGuidelines,
            $themeInfo->guidelineFile(),
            $activeExtensions
        );
        $guidelineContent .= $this->composeModuleInventory($projectRoot);

        foreach ($chosenAgents as $agent) {
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

        $themeFile = rtrim($resourcesDir, '/') . '/guidelines/' . $themeInfo->guidelineFile();
        if (file_exists($themeFile)) {
            $themeTarget = rtrim($projectRoot, '/') . '/.ai/guidelines/magento-boost';
            if (!is_dir($themeTarget)) {
                mkdir($themeTarget, 0755, true);
            }
            copy($themeFile, $themeTarget . '/theme.md');
            $output->writeln('  Theme guidelines (' . $themeInfo->label() . ') copied to <comment>.ai/guidelines/magento-boost/theme.md</comment>');
        }

        (new SkillComposer())->write($projectRoot, $resourcesDir, $chosenSkills, $activeExtensions);
        if ($chosenSkills || $activeExtensions) {
            $output->writeln('  Skills copied to <comment>.ai/skills/</comment>');
        }

        if ($activeExtensions) {
            $names = implode(', ', array_map(fn(BoostExtension $ext) => $ext->name, $activeExtensions));
            $output->writeln('  Extensions merged: <info>' . $names . '</info>');
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

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

    private function displayBanner(OutputInterface $output): void
    {
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
    }
}
