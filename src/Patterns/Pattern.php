<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\TransformationPatternScd\Parameters\Parameters;

interface Pattern
{
    public const TABLE_INPUT = 'input_table';
    public const TABLE_CURRENT_SNAPSHOT = 'current_snapshot';
    public const TABLE_NEW_SNAPSHOT = 'new_snapshot';
    public const COLUMN_SNAPSHOT_PK = 'snapshot_pk';

    public function getInputTableName(): string;

    public function getSnapshotInputTable(): string;

    public function getSnapshotOutputTable(): string;

    public function getSnapshotPrimaryKey(): string;

    public function getSnapshotTableHeader(): array;

    public function setParameters(Parameters $parameters): void;

    public function getParameters(): Parameters;

    public function render(): string;
}
