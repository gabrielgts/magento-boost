<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Extension;

class BoostExtension
{
    /**
     * @param string   $name           Composer package name, e.g. "ironplane/magento-boost-rules"
     * @param string[] $guidelineFiles Absolute paths to .md guideline files
     * @param string[] $skillDirs      Absolute paths to skill directories (each must contain SKILL.md)
     */
    public function __construct(
        public readonly string $name,
        public readonly array  $guidelineFiles,
        public readonly array  $skillDirs,
    ) {}

    public function guidelineCount(): int
    {
        return count($this->guidelineFiles);
    }

    public function skillCount(): int
    {
        return count($this->skillDirs);
    }
}
