<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\McpWriter;
use Symfony\Component\Process\Process;

class ClaudeCodeAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'claude-code';
    }

    public function label(): string
    {
        return 'Claude Code';
    }

    public function guidelineFile(): string
    {
        return 'CLAUDE.md';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        // Try the claude CLI first; fall back to writing .mcp.json directly.
        $claude = trim(shell_exec('which claude 2>/dev/null') ?? '');
        if ($claude !== '') {
            $process = new Process(
                ['claude', 'mcp', 'add', '-s', 'local', '-t', 'stdio', 'magento-boost', '--', PHP_BINARY, 'bin/magento', 'boost:mcp'],
                $projectRoot
            );
            $process->run();
            if ($process->isSuccessful()) {
                return;
            }
        }
        (new McpWriter())->write($projectRoot . '/.mcp.json', $magerunBin, $projectRoot);
    }
}
