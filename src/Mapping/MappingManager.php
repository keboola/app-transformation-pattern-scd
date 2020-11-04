<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Patterns\Pattern;

class MappingManager
{
    private InputMapping $inputMapping;

    private OutputMapping $outputMapping;

    public function __construct(Config $config, Pattern $pattern)
    {
        $this->inputMapping = new InputMapping($config, $pattern);
        $this->outputMapping = new OutputMapping($config, $pattern, $this->inputMapping);
    }

    public function getInputMapping(): InputMapping
    {
        return $this->inputMapping;
    }

    public function getOutputMapping(): OutputMapping
    {
        return $this->outputMapping;
    }
}
