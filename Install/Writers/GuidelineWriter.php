<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Writers;

class GuidelineWriter
{
    private const MARKER_START = '<!-- magento-boost:guidelines:start -->';
    private const MARKER_END   = '<!-- magento-boost:guidelines:end -->';

    /**
     * Merge guideline content into a Markdown file inside a managed fenced region.
     * Content outside the fenced region is preserved.
     */
    public function write(string $filePath, string $content): void
    {
        $this->ensureDirectory($filePath);

        $existing = file_exists($filePath) ? file_get_contents($filePath) : '';

        $block = self::MARKER_START . PHP_EOL
            . trim($content) . PHP_EOL
            . self::MARKER_END;

        if (str_contains($existing, self::MARKER_START)) {
            $updated = preg_replace(
                '/' . preg_quote(self::MARKER_START, '/') . '.*?' . preg_quote(self::MARKER_END, '/') . '/s',
                $block,
                $existing
            );
        } else {
            $updated = trim($existing) . ($existing !== '' ? PHP_EOL . PHP_EOL : '') . $block . PHP_EOL;
        }

        file_put_contents($filePath, $updated);
    }

    /**
     * Copy guideline source files into .ai/guidelines/magento-boost/.
     * Each source file maps 1:1 by basename.
     */
    public function writeShared(string $projectRoot, string $resourcesDir, array $packs): void
    {
        $target = rtrim($projectRoot, '/') . '/.ai/guidelines/magento-boost';
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        foreach ($packs as $pack) {
            $src = rtrim($resourcesDir, '/') . '/guidelines/' . $pack . '.md';
            if (file_exists($src)) {
                copy($src, $target . '/' . $pack . '.md');
            }
        }
    }

    private function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
