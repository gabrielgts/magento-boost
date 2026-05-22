<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Console\Command;

use Gtstudio\MagentoBoost\Install\BoostConfig;
use Gtstudio\MagentoBoost\Install\MagerunDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class McpStartCommand extends Command
{
    /** Tool groups to expose for each allowlist preset (magerun 9.4+ group filtering). */
    private const PRESETS = [
        'read-only'  => ['sys', 'config', 'dev', 'indexer', 'cache'],
        'dev-safe'   => ['sys', 'config', 'dev', 'indexer', 'cache', 'setup', 'magerun'],
        'everything' => [],
    ];

    protected function configure(): void
    {
        $this->setName('boost:mcp')
            ->setDescription('Start the magento-boost MCP server (delegates to n98-magerun2)')
            ->addOption('preset', null, InputOption::VALUE_OPTIONAL, 'Tool allowlist preset (read-only|dev-safe|everything)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = $this->getProjectRoot();

        // Resolve binary and preset from boost.json, then CLI option
        $config    = (new BoostConfig($projectRoot))->load();
        $mcpConfig = $config->get('mcp', []);
        $binary    = $mcpConfig['binary'] ?? null;
        $preset    = $input->getOption('preset') ?? $mcpConfig['preset'] ?? 'dev-safe';

        if (!$binary || !is_executable($binary)) {
            $detector = new MagerunDetector($projectRoot);
            $binary   = $detector->detect();
        }

        if (!$binary) {
            $output->writeln('<error>n98-magerun2 binary not found. Install via: composer require --dev n98/magerun2-dist:^9.4</error>');
            return self::FAILURE;
        }

        $cmd = [$binary, 'mcp:server:start'];

        // Append group filters for non-"everything" presets (magerun 9.4+)
        $groups = self::PRESETS[$preset] ?? [];
        foreach ($groups as $group) {
            $cmd[] = '--groups';
            $cmd[] = $group;
        }

        // Hand off via exec so stdio is passed through directly to the MCP client
        $process = new Process($cmd, $projectRoot);
        $process->setTty(false);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer) use ($output): void {
            if ($type === Process::ERR) {
                fwrite(STDERR, $buffer);
            } else {
                fwrite(STDOUT, $buffer);
            }
        });

        return $process->getExitCode() ?? self::SUCCESS;
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
