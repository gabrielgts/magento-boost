<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\McpWriter;
use Symfony\Component\Process\Process;

class CodexAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'codex';
    }

    public function label(): string
    {
        return 'OpenAI Codex CLI';
    }

    public function guidelineFile(): string
    {
        return 'AGENTS.md';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        $codex = trim(shell_exec('which codex 2>/dev/null') ?? '');
        if ($codex !== '') {
            $process = new Process(
                ['codex', 'mcp', 'add', 'magento-boost', '--', PHP_BINARY, 'bin/magento', 'boost:mcp'],
                $projectRoot
            );
            $process->run();
            if ($process->isSuccessful()) {
                return;
            }
        }
        // Fall back: write to .mcp.json which Codex also respects
        (new McpWriter())->write($projectRoot . '/.mcp.json', $magerunBin, $projectRoot);
    }
}
