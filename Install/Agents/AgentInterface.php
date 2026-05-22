<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

interface AgentInterface
{
    public function name(): string;

    public function label(): string;

    /** Path to a project-level guideline aggregator file (e.g. CLAUDE.md). */
    public function guidelineFile(): string;

    /** Register the MCP server for this agent given the magerun binary path. */
    public function registerMcp(string $projectRoot, string $magerunBin): void;

    /** Write or merge the guideline content into the agent's root file. */
    public function writeGuidelines(string $projectRoot, string $content): void;
}
