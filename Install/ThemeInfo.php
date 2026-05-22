<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install;

class ThemeInfo
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $version,
        public readonly ?string $customThemeName,
    ) {}

    public function label(): string
    {
        return match ($this->type) {
            'hyva'   => 'Hyvä' . ($this->version ? ' ' . $this->version : ''),
            'alpaca' => 'Alpaca' . ($this->version ? ' ' . $this->version : ''),
            default  => 'Luma/Blank',
        };
    }

    public function guidelineFile(): string
    {
        return match ($this->type) {
            'hyva'   => 'themes/hyva.md',
            'alpaca' => 'themes/alpaca.md',
            default  => 'themes/luma.md',
        };
    }
}
