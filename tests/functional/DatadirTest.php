<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;

class DatadirTest extends DatadirTestCase
{
    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestProvider($this->getTestFileDir()),
        ];
    }
}
