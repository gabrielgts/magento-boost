<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Writers;

class SkillWriter
{
    /**
     * Copy skill SKILL.md files from resources into .ai/skills/{name}/SKILL.md.
     */
    public function write(string $projectRoot, string $resourcesDir, array $skills): void
    {
        foreach ($skills as $skill) {
            $src = rtrim($resourcesDir, '/') . '/skills/' . $skill . '/SKILL.md';
            if (!file_exists($src)) {
                continue;
            }
            $target = rtrim($projectRoot, '/') . '/.ai/skills/' . $skill;
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
            copy($src, $target . '/SKILL.md');
        }
    }
}
