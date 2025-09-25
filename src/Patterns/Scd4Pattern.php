<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\TransformationPatternScd\Exception\ApplicationException;
use Keboola\TransformationPatternScd\Parameters\Parameters;

class Scd4Pattern extends AbstractPattern
{
    public const COLUMN_SNAPSHOT_DATE = 'snapshot_date';
    public const COLUMN_ACTUAL = 'actual';
    public const COLUMN_IS_DELETED = 'is_deleted';

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
                return 'Scd4Snowflake.twig';
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
            'inputColumns' => $this->getInputColumns(),
            'snapshotPrimaryKeyName' => $this->getSnapshotPrimaryKey(),
            'snapshotPrimaryKeyParts' => $this->getSnapshotPrimaryKeyParts(),
            'snapshotInputColumns' => $this->getSnapshotInputColumns(),
            'snapshotSpecialColumns' => $this->getSnapshotSpecialColumns(),
            'snapshotAllColumnsExceptPk' => $this->getSnapshotAllColumnsExceptPk(),
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

    private function getInputColumns(): array
    {
        return array_merge($this->getParameters()->getPrimaryKey(), $this->getParameters()->getMonitoredParameters());
    }

    public function getSnapshotPrimaryKeyParts(): array
    {
        // All snapshot columns are lower
        return $this->columnsToLower(
            array_merge($this->getParameters()->getPrimaryKey(), [self::COLUMN_SNAPSHOT_DATE])
        );
    }

    private function getSnapshotInputColumns(): array
    {
        // All snapshot columns are lower
        return $this->columnsToLower($this->getInputColumns());
    }

    public function getSnapshotSpecialColumns(): array
    {
        $columns[] = self::COLUMN_SNAPSHOT_DATE;
        $columns[] = self::COLUMN_ACTUAL;

        if ($this->getParameters()->hasDeletedFlag()) {
            $columns[] = self::COLUMN_IS_DELETED;
        }

        // All snapshot columns are lower
        return $this->columnsToLower($columns);
    }

    private function getSnapshotAllColumnsExceptPk(): array
    {
        return array_merge(
            $this->getSnapshotInputColumns(),
            $this->getSnapshotSpecialColumns()
        );
    }
}
