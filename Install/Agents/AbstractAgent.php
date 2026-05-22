<?php

declare(strict_types=1);

namespace Gtstudio\MagentoBoost\Install\Agents;

use Gtstudio\MagentoBoost\Install\Writers\GuidelineWriter;

abstract class AbstractAgent implements AgentInterface
{
    public function writeGuidelines(string $projectRoot, string $content): void
    {
        $file = rtrim($projectRoot, '/') . '/' . $this->guidelineFile();
        (new GuidelineWriter())->write($file, $content);
    }
}
