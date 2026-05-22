<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

class ModuleInventory
{
    private const SKIP_VENDORS = ['magento', 'magento2', 'Gtstudio'];
    private const CORE_PACKAGE_PREFIXES = ['magento/', 'magento2/', 'laminas/', 'league/', 'symfony/', 'doctrine/', 'composer/', 'wikimedia/'];
    private const MAX_THIRD_PARTY = 30;

    private string $root;
    private ?array $composerLock = null;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, '/');
    }

    /**
     * Returns Markdown text summarising this project's custom and third-party modules.
     * Kept intentionally compact — only what helps an AI understand the project.
     */
    public function summarize(): string
    {
        $custom      = $this->customModules();
        $thirdParty  = $this->thirdPartyModules();
        $parts       = [];

        if ($custom) {
            $parts[] = "## Project custom modules\n\n" . $this->renderCustomTable($custom);
        }

        if ($thirdParty) {
            $parts[] = "## Installed third-party modules\n\n" . $this->renderThirdPartyTable($thirdParty);
        }

        return implode("\n\n", $parts);
    }

    /** Returns [{vendor, module, description}] for app/code modules. */
    public function customModules(): array
    {
        $codeDir = $this->root . '/app/code';
        if (!is_dir($codeDir)) {
            return [];
        }
        $result = [];
        foreach ($this->listDirs($codeDir) as $vendor) {
            if (in_array($vendor, self::SKIP_VENDORS, true)) {
                continue;
            }
            foreach ($this->listDirs($codeDir . '/' . $vendor) as $module) {
                $description = $this->readModuleDescription($codeDir . '/' . $vendor . '/' . $module);
                $result[] = [
                    'vendor'      => $vendor,
                    'module'      => $module,
                    'description' => $description,
                ];
            }
        }
        return $result;
    }

    /** Returns [{name, description}] for composer.lock magento2-module packages (non-core). */
    public function thirdPartyModules(): array
    {
        $lock    = $this->composerLock();
        $result  = [];
        $all     = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        foreach ($all as $pkg) {
            if (($pkg['type'] ?? '') !== 'magento2-module') {
                continue;
            }
            $name = $pkg['name'] ?? '';
            if ($this->isCorePackage($name)) {
                continue;
            }
            $result[] = [
                'name'        => $name,
                'version'     => $pkg['version'] ?? '',
                'description' => $pkg['description'] ?? '',
            ];
        }
        // Group by vendor, sort, cap
        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));
        return array_slice($result, 0, self::MAX_THIRD_PARTY);
    }

    private function readModuleDescription(string $moduleDir): string
    {
        // 1. composer.json description
        $composerJson = $moduleDir . '/composer.json';
        if (file_exists($composerJson)) {
            $data = json_decode(file_get_contents($composerJson), true);
            $desc = trim($data['description'] ?? '');
            if ($desc !== '' && strtolower($desc) !== 'n/a') {
                return $desc;
            }
        }
        return '';
    }

    private function renderCustomTable(array $modules): string
    {
        $lines = ['| Module | Description |', '|--------|-------------|'];
        foreach ($modules as $m) {
            $name = $m['vendor'] . '_' . $m['module'];
            $desc = $m['description'] ?: $this->humanize($m['module']);
            $lines[] = '| `' . $name . '` | ' . $this->escape($desc) . ' |';
        }
        return implode("\n", $lines);
    }

    private function renderThirdPartyTable(array $modules): string
    {
        $lines = ['| Package | Description |', '|---------|-------------|'];
        foreach ($modules as $m) {
            $desc  = $this->truncate($m['description'] ?? '', 100);
            $lines[] = '| `' . $m['name'] . '` | ' . $this->escape($desc) . ' |';
        }
        return implode("\n", $lines);
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }

    private function humanize(string $moduleName): string
    {
        return trim(preg_replace('/([A-Z])/', ' $1', $moduleName));
    }

    private function escape(string $text): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $text);
    }

    private function isCorePackage(string $name): bool
    {
        foreach (self::CORE_PACKAGE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function listDirs(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        return array_values(array_filter(
            scandir($dir) ?: [],
            fn($e) => $e !== '.' && $e !== '..' && is_dir($dir . '/' . $e)
        ));
    }

    private function composerLock(): array
    {
        if ($this->composerLock !== null) {
            return $this->composerLock;
        }
        $file = $this->root . '/composer.lock';
        if (!file_exists($file)) {
            return $this->composerLock = [];
        }
        $decoded = json_decode(file_get_contents($file), true);
        return $this->composerLock = is_array($decoded) ? $decoded : [];
    }
}
