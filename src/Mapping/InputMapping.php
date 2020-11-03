<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Config;

class InputMapping
{
    public const SOURCE_TABLE_DESTINATION = 'in_table';
    public const SNAPSHOT_TABLE_DESTINATION = 'curr_snapshot';
    public const SNAPSHOT_TABLE_SOURCE_PREFIX = 'curr_snapshot_';

    private Config $config;

    private Table $sourceTable;

    private Table $snapshotTable;

    public function __construct(Config $config)
    {
        $this->config = $config;

        // Parse input mapping and find source table
        $this->parseInputMapping();
    }

    public function toArray(): array
    {
        return array_map(fn(Table $table) => $table->toArray(), $this->getTables());
    }

    public function getTables(): array
    {
        return [$this->getSourceTable(), $this->getSnapshotTable()];
    }

    public function getSourceTable(): Table
    {
        return $this->sourceTable;
    }

    public function getSnapshotTable(): Table
    {
        return $this->snapshotTable;
    }

    /**
     * In input mapping are expected two tables with destination:
     * "in_table", and "curr_snapshot", all others are ignored.
     *
     * If only one table is present, it is taken as the "in_table" and the "curr_snapshot" table is added.
     *
     * If there is no table in the input mapping, then an exception is thrown.
     */
    private function parseInputMapping(): void
    {
        $imTables = $this->config->getInputTables();

        // No table in input mapping
        if (count($imTables) === 0) {
            throw new UserException('Please specify one input table in the input mapping.');
        }

        // One table in input mapping -> it is source table, we rewrite the destination
        if (count($imTables) === 1) {
            $data = $imTables[0];
            $data['destination'] = self::SOURCE_TABLE_DESTINATION;
            $this->sourceTable = $this->createTable($data);
            $this->generateSnapshotTable();
            return;
        }

        // Multiple tables in input mapping, we need to find source table by "destination" = "in_table"
        $sourceTableFound = false;
        $snapshotTableFound = false;
        foreach ($imTables as $data) {
            switch ($data['destination'] ?? null) {
                // Found destination table
                case self::SOURCE_TABLE_DESTINATION:
                    if ($sourceTableFound === true) {
                        throw new UserException(sprintf(
                            'Found multiple tables with "destination" = "%s" in input mapping, but only one allowed.',
                            self::SOURCE_TABLE_DESTINATION
                        ));
                    }
                    $sourceTableFound = true;
                    $this->sourceTable = $this->createTable($data);
                    break;

                // Found snapshot table
                case self::SNAPSHOT_TABLE_DESTINATION:
                    if ($snapshotTableFound === true) {
                        throw new UserException(sprintf(
                            'Found multiple tables with "destination" = "%s" in input mapping, but only one allowed.',
                            self::SNAPSHOT_TABLE_DESTINATION
                        ));
                    }
                    $snapshotTableFound = true;
                    $this->setSnapshotTableFilter($data);
                    $this->snapshotTable = $this->createTable($data);
                    break;

                default:
                    // Other tables are not expected and ignored
                    // Will not be included in the generated input mapping
            }
        }

        // No source table found -> error
        if ($sourceTableFound === false) {
            throw new UserException(sprintf(
                'Found "%d" tables in input mapping, but no source table with "destination" = "%s". ' .
                'Please set the source table in the input mapping.',
                count($imTables),
                self::SOURCE_TABLE_DESTINATION
            ));
        }

        // No snapshot table found -> generate default
        if ($snapshotTableFound === false) {
            $this->generateSnapshotTable();
        }
    }

    private function generateSnapshotTable(): void
    {
        $data = [
            'source' => sprintf(
                '%s.%s%s',
                $this->getSourceTable()->getBuckedId(),
                self::SNAPSHOT_TABLE_SOURCE_PREFIX,
                $this->getSourceTable()->getTableName()
            ),
            'destination' => self::SNAPSHOT_TABLE_DESTINATION,
        ];
        $this->setSnapshotTableFilter($data);
        $this->snapshotTable = $this->createTable($data);
    }

    private function setSnapshotTableFilter(array &$data): void
    {
        $data['whereColumn'] = 'actual';
        $data['whereValues'] = ['1'];
        $data['whereOperator'] = 'eq';
    }

    private function createTable(array $data): Table
    {
        return new Table($data, Table::MAPPING_TYPE_INPUT);
    }
}
