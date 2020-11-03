<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\TransformationPatternScd\Mapping\MappingManager;

class StorageGenerator
{
    private MappingManager $mappingManager;

    public function __construct(MappingManager $mappingManager)
    {
        $this->mappingManager = $mappingManager;
    }

    public function generate(): array
    {
        return [
            'input' => [
                'tables' => $this->mappingManager->getInputMapping()->toArray(),
            ],
            'output' => [
                'tables' => $this->mappingManager->getOutputMapping()->toArray(),
            ],
        ];
    }
}
