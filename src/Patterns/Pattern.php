<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\TransformationPatternScd\Parameters\Parameters;

interface Pattern
{
    public function getInputTableName(): string;

    public function getSnapshotInputTable(): string;

    public function getSnapshotOutputTable(): string;

    public function getSnapshotPrimaryKey(): string;

    public function getSnapshotTableHeader(): array;

    public function setParameters(Parameters $parameters): void;

    public function render(): string;
}
