<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Symfony\Component\Process\Process;
use Gtstudio\MagentoBoost\Install\Writers\McpWriter;

class GeminiAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Gemini CLI';
    }

    public function guidelineFile(): string
    {
        return 'GEMINI.md';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        $gemini = trim(shell_exec('which gemini 2>/dev/null') ?? '');
        if ($gemini !== '') {
            $process = new Process(
                ['gemini', 'mcp', 'add', '-s', 'project', '-t', 'stdio', 'magento-boost', PHP_BINARY, 'bin/magento', 'boost:mcp'],
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
