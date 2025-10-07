<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

class Scd4Pattern extends AbstractPattern
{
    public const COLUMN_SNAPSHOT_DATE = 'snapshot_date';
    public const COLUMN_ACTUAL = 'actual';
    public const COLUMN_IS_DELETED = 'is_deleted';
    protected string $templateName = 'Scd4Snowflake.twig';

    public function getInputTableName(): string
    {
        return self::TABLE_INPUT;
    }

    public function getSnapshotInputTable(): string
    {
        return self::TABLE_CURRENT_SNAPSHOT;
    }

    public function getSnapshotOutputTable(): string
    {
        return self::TABLE_NEW_SNAPSHOT;
    }

    public function getSnapshotPrimaryKey(): string
    {
        return mb_strtolower(self::COLUMN_SNAPSHOT_PK);
    }

    protected function getTemplateVariables(): array
    {
        return [
            'timezone' => $this->getParameters()->getTimezone(),
            'useDatetime' => $this->getParameters()->useDatetime(),
            'keepDeleteActive' => $this->getParameters()->keepDeleteActive(),
            'hasDeletedFlag' => $this->getParameters()->hasDeletedFlag(),
            'inputPrimaryKey' => $this->getParameters()->getPrimaryKey(),
            'inputColumns' => array_map(fn($col) => $col['name'], $this->getInputColumns()),
            'snapshotPrimaryKeyName' => $this->getSnapshotPrimaryKey(),
            'snapshotPrimaryKeyParts' => array_map(fn($col) => $col['name'], $this->getSnapshotPrimaryKeyParts()),
            'snapshotInputColumns' => array_map(fn($col) => $col['name'], $this->getSnapshotInputColumns()),
            'snapshotSpecialColumns' => array_map(fn($col) => $col['name'], $this->getSnapshotSpecialColumns()),
            'snapshotAllColumnsExceptPk' => array_map(fn($col) => $col['name'], $this->getSnapshotAllColumnsExceptPk()),
            'deletedActualValue' => $this->getParameters()->keepDeleteActive() ? 1 : 0,
            'generateDeletedRecords' =>
                $this->getParameters()->hasDeletedFlag() || $this->getParameters()->keepDeleteActive(),
            'tableName' => [
                'input' => self::TABLE_INPUT,
                'currentSnapshot' => self::TABLE_CURRENT_SNAPSHOT,
                'newSnapshot' => self::TABLE_NEW_SNAPSHOT,
            ],
            'columnName' => [
                'snapshotDate' => self::COLUMN_SNAPSHOT_DATE,
                'actual' => self::COLUMN_ACTUAL,
                'isDeleted' => self::COLUMN_IS_DELETED,
            ],
        ];
    }

    private function getSnapshotPrimaryKeyParts(): array
    {
        $columns = $this->getColumnsWithDefinition(
            array_merge($this->getParameters()->getPrimaryKey(), [self::COLUMN_SNAPSHOT_DATE])
        );

        // All snapshot columns are lower
        return $this->columnsToLower($columns);
    }

    protected function getSnapshotSpecialColumns(): array
    {
        $columns[] = [
            'name' => self::COLUMN_SNAPSHOT_DATE,
            'definition' => ['type' => $this->getParameters()->useDatetime() ? 'DATETIME' : 'DATE'],
        ];

        $columns[] = [
            'name' => self::COLUMN_ACTUAL,
            'definition' => ['type' => 'VARCHAR'],
        ];

        if ($this->getParameters()->hasDeletedFlag()) {
            $columns[] = $this->getDeletedColumn(self::COLUMN_IS_DELETED);
        }

        // All snapshot columns are lower
        return $this->columnsToLower($columns);
    }
}
