<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\McpWriter;

class VsCodeAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'vscode';
    }

    public function label(): string
    {
        return 'VS Code / GitHub Copilot';
    }

    public function guidelineFile(): string
    {
        return '.github/copilot-instructions.md';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        $dir = $projectRoot . '/.vscode';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        (new McpWriter())->write($dir . '/mcp.json', $magerunBin, $projectRoot);
    }
}
