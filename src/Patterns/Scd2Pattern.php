<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

class Scd2Pattern extends AbstractPattern
{
    protected string $templateName = 'Scd2Snowflake.twig';

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
        return $this->getParameters()->getUppercaseColumns()
            ? mb_strtoupper(self::COLUMN_SNAPSHOT_PK)
            : self::COLUMN_SNAPSHOT_PK;
    }

    protected function getTemplateVariables(): array
    {
        $inputPrimaryKeyLower =  $this->columnsToLower(
            $this->getColumnsWithDefinition($this->getParameters()->getPrimaryKey()),
        );
        return [
            'timezone' => $this->getParameters()->getTimezone(),
            'useDatetime' => $this->getParameters()->useDatetime(),
            'keepDeleteActive' => $this->getParameters()->keepDeleteActive(),
            'hasDeletedFlag' => $this->getParameters()->hasDeletedFlag(),
            'inputPrimaryKey' => $this->getParameters()->getPrimaryKey(),
            'inputPrimaryKeyLower' => array_map(fn($col) => $col['name'], $inputPrimaryKeyLower),
            'inputColumns' => array_map(fn($col) => $col['name'], $this->getInputColumns()),
            'snapshotPrimaryKeyName' => $this->getSnapshotPrimaryKey(),
            'snapshotPrimaryKeyParts' => array_map(fn($col) => $col['name'], $this->getSnapshotPrimaryKeyParts()),
            'snapshotInputColumns' => array_map(fn($col) => $col['name'], $this->getSnapshotInputColumns()),
            'snapshotAllColumnsExceptPk' => array_map(fn($col) => $col['name'], $this->getSnapshotAllColumnsExceptPk()),
            'deletedActualValue' => $this->getParameters()->keepDeleteActive()
                ? $this->getParameters()->getDeletedFlagValue()[1]
                : $this->getParameters()->getDeletedFlagValue()[0],
            'tableName' => [
                'input' => self::TABLE_INPUT,
                'currentSnapshot' => self::TABLE_CURRENT_SNAPSHOT,
                'newSnapshot' => self::TABLE_NEW_SNAPSHOT,
            ],
            'columnName' => array_map(fn($col) => $col['name'], $this->getSnapshotSpecialColumnsWithKeys()),
            'endDateValue' => $this->getParameters()->getEndDateValue(),
            'deletedFlagValue' => $this->getParameters()->getDeletedFlagValue(),
            'currentTimestampMinusOne' => $this->getParameters()->getCurrentTimestampMinusOne(),
            'uppercaseColumns' => $this->getParameters()->getUppercaseColumns(),
            'effectiveDateAdjustment' => $this->getParameters()->getEffectiveDateAdjustment(),
        ];
    }

    private function getSnapshotPrimaryKeyParts(): array
    {
        // All snapshot columns are lower
        $columns = $this->getColumnsWithDefinition(
            array_merge($this->getParameters()->getPrimaryKey(), [$this->getParameters()->getStartDateName()])
        );

        return $this->columnsToLower($columns);
    }

    protected function getSnapshotSpecialColumns(): array
    {
        return array_values($this->getSnapshotSpecialColumnsWithKeys());
    }

    private function getSnapshotSpecialColumnsWithKeys(): array
    {
        $columns['startDate'] = [
            'name' => $this->getParameters()->getStartDateName(),
            'definition' => ['type' => $this->getParameters()->useDatetime() ? 'DATETIME' : 'DATE'],
        ];

        $columns['endDate'] = [
            'name' => $this->getParameters()->getEndDateName(),
            'definition' => ['type' => $this->getParameters()->useDatetime() ? 'DATETIME' : 'DATE'],
        ];

        $columns['actual'] = [
            'name' => $this->getParameters()->getActualName(),
            'definition' => ['type' => 'VARCHAR'],
        ];

        if ($this->getParameters()->hasDeletedFlag()) {
            $columns['isDeleted'] = $this->getDeletedColumn();
        }

        return $this->getParameters()->getUppercaseColumns() ?
            $this->columnsToUpper($columns) :
            $this->columnsToLower($columns);
    }
}
