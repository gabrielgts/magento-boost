<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

use Gtstudio\MagentoBoost\Install\Extension\BoostExtension;

class GuidelineComposer
{
    /**
     * Build the full guideline content string:
     * 1. Core packs (core, frontend, commerce-cloud)
     * 2. Theme-specific guidelines
     * 3. Each extension's guideline files in discovery order
     *
     * @param BoostExtension[] $extensions
     */
    public function compose(
        string $resourcesDir,
        array  $packs,
        string $themeGuidelineFile,
        array  $extensions = [],
    ): string {
        $parts = [];

        foreach ($packs as $pack) {
            $file = rtrim($resourcesDir, '/') . '/guidelines/' . $pack . '.md';
            if (file_exists($file)) {
                $parts[] = file_get_contents($file);
            }
        }

        $themeFile = rtrim($resourcesDir, '/') . '/guidelines/' . $themeGuidelineFile;
        if (file_exists($themeFile)) {
            $parts[] = file_get_contents($themeFile);
        }

        foreach ($extensions as $extension) {
            foreach ($extension->guidelineFiles as $guidelineFile) {
                if (file_exists($guidelineFile)) {
                    $parts[] = file_get_contents($guidelineFile);
                }
            }
        }

        return implode(PHP_EOL . PHP_EOL, array_filter($parts));
    }
}
