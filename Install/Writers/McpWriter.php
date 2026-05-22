<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Writers;

class McpWriter
{
    private const SERVER_KEY = 'magento-boost';

    /**
     * Merge the magento-boost MCP server entry into a JSON config file.
     * Creates the file if it does not exist. Preserves all other keys.
     */
    public function write(string $filePath, string $magerunBin, string $projectRoot): void
    {
        $this->ensureDirectory($filePath);

        $config = [];
        if (file_exists($filePath)) {
            $decoded = json_decode(file_get_contents($filePath), true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        if (!isset($config['mcpServers'])) {
            $config['mcpServers'] = [];
        }

        $config['mcpServers'][self::SERVER_KEY] = [
            'command' => PHP_BINARY,
            'args'    => ['bin/magento', 'boost:mcp'],
            'cwd'     => $projectRoot,
        ];

        file_put_contents(
            $filePath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    /** Remove the magento-boost entry from a JSON MCP config file. */
    public function remove(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }
        $config = json_decode(file_get_contents($filePath), true);
        if (!is_array($config)) {
            return;
        }
        unset($config['mcpServers'][self::SERVER_KEY]);
        file_put_contents(
            $filePath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function ensureDirectory(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
