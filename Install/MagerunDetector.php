<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

use Symfony\Component\Process\Process;

class MagerunDetector
{
    private const PROBE_PATHS = [
        'vendor/bin/n98-magerun2',
        'bin/n98-magerun2.phar',
        'bin/n98-magerun2',
        'n98-magerun2',
    ];

    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, '/');
    }

    /** Returns the resolved binary path or null if not found. */
    public function detect(): ?string
    {
        foreach (self::PROBE_PATHS as $candidate) {
            $path = $candidate;
            if (!str_starts_with($candidate, '/') && !str_starts_with($candidate, 'n98')) {
                $path = $this->root . '/' . $candidate;
            }
            if (is_executable($path)) {
                return $path;
            }
            // Try system PATH for bare binary names
            if (!str_contains($candidate, '/')) {
                $which = trim(shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null') ?? '');
                if ($which !== '') {
                    return $which;
                }
            }
        }
        return null;
    }

    /** Returns the version string from the binary, or null on failure. */
    public function version(string $binary): ?string
    {
        $process = new Process([$binary, '--version', '--no-interaction'], $this->root);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        // Output looks like: "n98-magerun2 9.4.0 by ..."
        if (preg_match('/\b(\d+\.\d+\.\d+)\b/', $process->getOutput(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function meetsMinimum(string $version, string $minimum = '9.4.0'): bool
    {
        return version_compare($version, $minimum, '>=');
    }
}
