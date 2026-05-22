<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\McpWriter;

class JunieAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'junie';
    }

    public function label(): string
    {
        return 'Junie (JetBrains)';
    }

    public function guidelineFile(): string
    {
        return '.junie/guidelines.md';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        // Junie uses .mcp.json at the project root
        (new McpWriter())->write($projectRoot . '/.mcp.json', $magerunBin, $projectRoot);
    }

    public function writeGuidelines(string $projectRoot, string $content): void
    {
        $dir = rtrim($projectRoot, '/') . '/.junie';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        parent::writeGuidelines($projectRoot, $content);
    }
}
