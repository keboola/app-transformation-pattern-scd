<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\TransformationPatternScd\Config;

class MappingManager
{
    private InputMapping $inputMapping;

    private OutputMapping $outputMapping;

    public function __construct(Config $config)
    {
        $this->inputMapping = new InputMapping($config);
        $this->outputMapping = new OutputMapping($config, $this->inputMapping);
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
