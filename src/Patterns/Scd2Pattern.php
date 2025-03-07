<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\TransformationPatternScd\Exception\ApplicationException;
use Keboola\TransformationPatternScd\Parameters\Parameters;

class Scd2Pattern extends AbstractPattern
{
    public const TABLE_INPUT = 'input_table';
    public const TABLE_CURRENT_SNAPSHOT = 'current_snapshot';
    public const TABLE_NEW_SNAPSHOT = 'new_snapshot';

    public const COLUMN_SNAPSHOT_PK = 'snapshot_pk';

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

    public function getSnapshotTableHeader(): array
    {
        return array_merge(
            [$this->getSnapshotPrimaryKey()],
            $this->getSnapshotAllColumnsExceptPk()
        );
    }

    protected function getTemplatePath(): string
    {
        $backend = $this->getParameters()->getBackend();
        switch ($backend) {
            case Parameters::BACKEND_SNOWFLAKE:
                return 'Scd2Snowflake.twig';
            default:
                throw new ApplicationException(sprintf('Unexpected backend "%s".', $backend));
        }
    }

    protected function getTemplateVariables(): array
    {
        return [
            'timezone' => $this->getParameters()->getTimezone(),
            'useDatetime' => $this->getParameters()->useDatetime(),
            'keepDeleteActive' => $this->getParameters()->keepDeleteActive(),
            'hasDeletedFlag' => $this->getParameters()->hasDeletedFlag(),
            'inputPrimaryKey' => $this->getParameters()->getPrimaryKey(),
            'inputPrimaryKeyLower' => $this->columnsToLower($this->getParameters()->getPrimaryKey()),
            'inputColumns' => $this->getInputColumns(),
            'snapshotPrimaryKeyName' => $this->getSnapshotPrimaryKey(),
            'snapshotPrimaryKeyParts' => $this->getSnapshotPrimaryKeyParts(),
            'snapshotInputColumns' => $this->getSnapshotInputColumns(),
            'snapshotAllColumnsExceptPk' => $this->getSnapshotAllColumnsExceptPk(),
            'deletedActualValue' => $this->getParameters()->keepDeleteActive()
                ? $this->getParameters()->getDeletedFlagValue()[1]
                : $this->getParameters()->getDeletedFlagValue()[0],
            'tableName' => [
                'input' => self::TABLE_INPUT,
                'currentSnapshot' => self::TABLE_CURRENT_SNAPSHOT,
                'newSnapshot' => self::TABLE_NEW_SNAPSHOT,
            ],
            'columnName' => $this->getSnapshotSpecialColumnsWithKeys(),
            'endDateValue' => $this->getParameters()->getEndDateValue(),
            'deletedFlagValue' => $this->getParameters()->getDeletedFlagValue(),
            'currentTimestampMinusOne' => $this->getParameters()->getCurrentTimestampMinusOne(),
            'uppercaseColumns' => $this->getParameters()->getUppercaseColumns(),
        ];
    }

    private function getInputColumns(): array
    {
        return array_merge($this->getParameters()->getPrimaryKey(), $this->getParameters()->getMonitoredParameters());
    }

    private function getSnapshotPrimaryKeyParts(): array
    {
        // All snapshot columns are lower
        return $this->columnsToLower(
            array_merge($this->getParameters()->getPrimaryKey(), [$this->getParameters()->getStartDateName()])
        );
    }

    private function getSnapshotInputColumns(): array
    {
        return $this->getParameters()->getUppercaseColumns()
            ? $this->columnsToUpper($this->getInputColumns())
            : $this->columnsToLower($this->getInputColumns());
    }

    private function getSnapshotSpecialColumns(): array
    {
        return array_values($this->getSnapshotSpecialColumnsWithKeys());
    }

    private function getSnapshotSpecialColumnsWithKeys(): array
    {
        $columns['startDate'] = $this->getParameters()->getStartDateName();
        $columns['endDate'] = $this->getParameters()->getEndDateName();
        $columns['actual'] = $this->getParameters()->getActualName();

        if ($this->getParameters()->hasDeletedFlag()) {
            $columns['isDeleted'] = $this->getParameters()->getIsDeletedName();
        }

        return $this->getParameters()->getUppercaseColumns()
            ? $this->columnsToUpper($columns)
            : $this->columnsToLower($columns);
    }

    private function getSnapshotAllColumnsExceptPk(): array
    {
        return array_merge(
            $this->getSnapshotInputColumns(),
            $this->getSnapshotSpecialColumns()
        );
    }
}
