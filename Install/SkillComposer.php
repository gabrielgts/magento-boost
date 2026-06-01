<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

use Gtstudio\MagentoBoost\Install\Extension\BoostExtension;
use Gtstudio\MagentoBoost\Install\Writers\SkillWriter;

class SkillComposer
{
    /**
     * Write core skills then copy each extension's skill directories.
     *
     * @param string[]         $skills     Core skill names (e.g. "module-scaffolding")
     * @param BoostExtension[] $extensions
     */
    public function write(
        string $projectRoot,
        string $resourcesDir,
        array  $skills,
        array  $extensions = [],
    ): void {
        if ($skills) {
            (new SkillWriter())->write($projectRoot, $resourcesDir, $skills);
        }

        foreach ($extensions as $extension) {
            foreach ($extension->skillDirs as $skillDir) {
                $skillName = basename($skillDir);
                $target    = rtrim($projectRoot, '/') . '/.ai/skills/' . $skillName;
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                copy($skillDir . '/SKILL.md', $target . '/SKILL.md');
            }
        }
    }
}
