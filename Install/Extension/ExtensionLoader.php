<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Extension;

class ExtensionLoader
{
    private const EXTRA_KEY = 'magento-boost';

    /**
     * Discover all boost extensions declared in composer.lock via extra.magento-boost.
     *
     * @return BoostExtension[]
     */
    public function load(string $projectRoot): array
    {
        $root = rtrim($projectRoot, '/');
        $lock = $this->readComposerLock($root);
        if (empty($lock)) {
            return [];
        }

        $extensions = [];
        $all         = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($all as $pkg) {
            $name  = $pkg['name'] ?? '';
            $extra = $pkg['extra'][self::EXTRA_KEY] ?? null;

            if (!$name || !is_array($extra)) {
                continue;
            }

            $pkgDir = $root . '/vendor/' . $name;
            if (!is_dir($pkgDir)) {
                continue;
            }

            $guidelineFiles = $this->resolveFiles($pkgDir, $extra['guidelines'] ?? []);
            $skillDirs      = $this->resolveSkillDirs($pkgDir, $extra['skills'] ?? []);

            if (empty($guidelineFiles) && empty($skillDirs)) {
                continue;
            }

            $extensions[] = new BoostExtension($name, $guidelineFiles, $skillDirs);
        }

        return $extensions;
    }

    /**
     * Filter a list of extensions by excluding names in the exclusion list.
     *
     * @param BoostExtension[] $extensions
     * @param string[]         $excluded
     * @return BoostExtension[]
     */
    public function filter(array $extensions, array $excluded): array
    {
        if (empty($excluded)) {
            return $extensions;
        }
        return array_values(array_filter(
            $extensions,
            fn(BoostExtension $ext) => !in_array($ext->name, $excluded, true)
        ));
    }

    /** @return string[] Existing absolute file paths from the declared list */
    private function resolveFiles(string $pkgDir, array $declared): array
    {
        $result = [];
        foreach ($declared as $rel) {
            $abs = $pkgDir . '/' . ltrim((string) $rel, '/');
            if (file_exists($abs) && is_file($abs)) {
                $result[] = $abs;
            }
        }
        return $result;
    }

    /** @return string[] Existing absolute skill directory paths */
    private function resolveSkillDirs(string $pkgDir, array $declared): array
    {
        $result = [];
        foreach ($declared as $rel) {
            $abs = $pkgDir . '/' . ltrim((string) $rel, '/');
            if (is_dir($abs) && file_exists($abs . '/SKILL.md')) {
                $result[] = $abs;
            }
        }
        return $result;
    }

    private function readComposerLock(string $root): array
    {
        $file = $root . '/composer.lock';
        if (!file_exists($file)) {
            return [];
        }
        $decoded = json_decode(file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }
}
