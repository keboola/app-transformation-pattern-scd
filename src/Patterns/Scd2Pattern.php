<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Patterns;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Application;

class Scd2Pattern extends AbstractPattern
{
    public const TABLE_INPUT = 'input_table';
    public const TABLE_CURRENT_SNAPSHOT = 'current_snapshot';
    public const TABLE_NEW_SNAPSHOT = 'new_snapshot';

    public const COLUMN_SNAPSHOT_PK = 'snapshot_pk';
    public const COLUMN_START_DATE = 'start_date';
    public const COLUMN_END_DATE = 'end_date';
    public const COLUMN_ACTUAL = 'actual';
    public const COLUMN_IS_DELETED = 'is_deleted';

    public function getTemplatePath(): string
    {
        switch ($this->config->getComponentId()) {
            case Application::SNOWFLAKE_TRANS_COMPONENT:
                return 'Scd2Snowflake.twig';
            case Application::SYNAPSE_TRANS_COMPONENT:
                return 'Scd2Synapse.twig';
            default:
                throw new UserException(sprintf(
                    'The SCD code pattern is not compatible with component "%s".',
                    $this->config->getComponentId()
                ));
        }
    }

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

    public function getTemplateVariables(): array
    {
        return [
            'config' => $this->config,
            'inputPrimaryKey' => $this->getInputPrimaryKey(),
            'inputPrimaryKeyLower' => $this->columnsToLower($this->getInputPrimaryKey()),
            'inputColumns' => $this->getInputColumns(),
            'snapshotPrimaryKeyName' => $this->getSnapshotPrimaryKey(),
            'snapshotPrimaryKeyParts' => $this->getSnapshotPrimaryKeyParts(),
            'snapshotInputColumns' => $this->getSnapshotInputColumns(),
            'snapshotSpecialColumns' => $this->getSnapshotSpecialColumns(),
            'snapshotAllColumnsExceptPk' => $this->getSnapshotAllColumnsExceptPk(),
            'deletedActualValue' => $this->config->keepDeleteActive() ? 1 : 0,
            'tableName' => [
                'input' => self::TABLE_INPUT,
                'currentSnapshot' => self::TABLE_CURRENT_SNAPSHOT,
                'newSnapshot' => self::TABLE_NEW_SNAPSHOT,
            ],
            'columnName' => [
                'startDate' => self::COLUMN_START_DATE,
                'endDate' => self::COLUMN_END_DATE,
                'actual' => self::COLUMN_ACTUAL,
                'isDeleted' => self::COLUMN_IS_DELETED,
            ],
        ];
    }

    private function getInputPrimaryKey(): array
    {
        return $this->config->getPrimaryKey();
    }

    private function getInputColumns(): array
    {
        return array_merge($this->config->getPrimaryKey(), $this->config->getMonitoredParameters());
    }

    private function getSnapshotPrimaryKeyParts(): array
    {
        // All snapshot columns are lower
        return $this->columnsToLower(array_merge($this->config->getPrimaryKey(), [self::COLUMN_START_DATE]));
    }

    private function getSnapshotInputColumns(): array
    {
        // All snapshot columns are lower
        return $this->columnsToLower($this->getInputColumns());
    }

    private function getSnapshotSpecialColumns(): array
    {
        $columns[] = self::COLUMN_START_DATE;
        $columns[] = self::COLUMN_END_DATE;
        $columns[] = self::COLUMN_ACTUAL;

        if ($this->config->hasDeletedFlag()) {
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

    private function columnsToLower(array $columns): array
    {
        return array_map(fn(string $column) => mb_strtolower($column), $columns);
    }
}
