<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;

class PatternFactory
{
    private string $scdType;

    public function __construct(string $scdType)
    {
        $this->scdType = $scdType;
    }

    public function create(): Pattern
    {
        switch ($this->scdType) {
            case GenerateDefinition::SCD_TYPE_2:
                return new Scd2Pattern();
            case GenerateDefinition::SCD_TYPE_4:
                return new Scd4Pattern();
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->scdType)
                );
        }
    }
}
