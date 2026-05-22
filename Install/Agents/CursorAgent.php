<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\McpWriter;

class CursorAgent extends AbstractAgent
{
    public function name(): string
    {
        return 'cursor';
    }

    public function label(): string
    {
        return 'Cursor';
    }

    public function guidelineFile(): string
    {
        return '.cursor/rules/magento-boost.mdc';
    }

    public function registerMcp(string $projectRoot, string $magerunBin): void
    {
        $dir = $projectRoot . '/.cursor';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        (new McpWriter())->write($dir . '/mcp.json', $magerunBin, $projectRoot);
    }

    public function writeGuidelines(string $projectRoot, string $content): void
    {
        $file = rtrim($projectRoot, '/') . '/' . $this->guidelineFile();
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Cursor MDC format: frontmatter + content
        if (!file_exists($file)) {
            file_put_contents($file, "---\ndescription: Magento 2 guidelines for AI\nalwaysApply: true\n---\n\n");
        }
        parent::writeGuidelines($projectRoot, $content);
    }
}
