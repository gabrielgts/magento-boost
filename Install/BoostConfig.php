<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

class BoostConfig
{
    private string $path;
    private array $data = [];

    public function __construct(string $projectRoot)
    {
        $this->path = rtrim($projectRoot, '/') . '/boost.json';
    }

    public function load(): self
    {
        if (file_exists($this->path)) {
            $decoded = json_decode(file_get_contents($this->path), true);
            if (is_array($decoded)) {
                $this->data = $decoded;
            }
        }
        return $this;
    }

    public function save(): void
    {
        file_put_contents(
            $this->path,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /** @return string[] Package names excluded from extension merging */
    public function getExtensionsExcluded(): array
    {
        return $this->get('extensions', [])['excluded'] ?? [];
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
