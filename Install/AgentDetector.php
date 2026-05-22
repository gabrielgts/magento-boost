<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

class AgentDetector
{
    /** Markers that indicate a particular AI agent is already configured. */
    private const MARKERS = [
        'claude-code' => ['.claude/'],
        'codex'       => ['.codex/', 'AGENTS.md'],
        'cursor'      => ['.cursor/'],
        'vscode'      => ['.vscode/'],
        'gemini'      => ['.gemini/'],
        'junie'       => ['.junie/'],
    ];

    /** Generic file presence markers (shown in summary regardless of agent). */
    private const GENERIC = [
        'CLAUDE.md',
        'AGENTS.md',
        '.mcp.json',
        'boost.json',
    ];

    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, '/');
    }

    /** Returns agent names that already have a config marker present. */
    public function detected(): array
    {
        $found = [];
        foreach (self::MARKERS as $agent => $markers) {
            foreach ($markers as $marker) {
                if (file_exists($this->root . '/' . $marker)) {
                    $found[] = $agent;
                    break;
                }
            }
        }
        return array_unique($found);
    }

    /** Returns generic AI-related files already present at the project root. */
    public function detectedFiles(): array
    {
        $found = [];
        foreach (self::GENERIC as $file) {
            if (file_exists($this->root . '/' . $file)) {
                $found[] = $file;
            }
        }
        return $found;
    }
}
