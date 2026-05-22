<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

class ThemeDetector
{
    private const HYVA_PACKAGES = [
        'hyva-themes/magento2-theme-module',
        'hyva-themes/magento2-default-theme',
        'hyva-themes/magento2-cms-theme-module',
    ];

    private const ALPACA_PACKAGES = [
        'snowdog/theme-frontend-alpaca',
        'snowdog/frontools',
    ];

    private string $root;
    private ?array $composerLock = null;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, '/');
    }

    public function detect(): ThemeInfo
    {
        $lock = $this->composerLock();

        if ($this->hasPackage($lock, self::HYVA_PACKAGES)) {
            $version = $this->packageVersion($lock, 'hyva-themes/magento2-theme-module')
                ?? $this->packageVersion($lock, 'hyva-themes/magento2-default-theme');
            return new ThemeInfo('hyva', $version, $this->customThemeName());
        }

        if ($this->hasPackage($lock, self::ALPACA_PACKAGES)) {
            $version = $this->packageVersion($lock, 'snowdog/theme-frontend-alpaca');
            return new ThemeInfo('alpaca', $version, $this->customThemeName());
        }

        return new ThemeInfo('luma', null, $this->customThemeName());
    }

    private function customThemeName(): ?string
    {
        $designDir = $this->root . '/app/design/frontend';
        if (!is_dir($designDir)) {
            return null;
        }
        $vendors = array_filter(scandir($designDir), fn($e) => $e !== '.' && $e !== '..' && $e !== 'Magento');
        foreach ($vendors as $vendor) {
            $themes = array_filter(
                scandir($designDir . '/' . $vendor) ?: [],
                fn($e) => $e !== '.' && $e !== '..'
            );
            foreach ($themes as $theme) {
                return $vendor . '/' . $theme;
            }
        }
        return null;
    }

    private function hasPackage(array $lock, array $candidates): bool
    {
        foreach ($candidates as $name) {
            if ($this->packageVersion($lock, $name) !== null) {
                return true;
            }
        }
        return false;
    }

    private function packageVersion(array $lock, string $name): ?string
    {
        foreach ($lock['packages'] ?? [] as $pkg) {
            if (($pkg['name'] ?? '') === $name) {
                return $pkg['version'] ?? 'unknown';
            }
        }
        foreach ($lock['packages-dev'] ?? [] as $pkg) {
            if (($pkg['name'] ?? '') === $name) {
                return $pkg['version'] ?? 'unknown';
            }
        }
        return null;
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
