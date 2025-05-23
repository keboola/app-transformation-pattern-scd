<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\TableIdGenerator;

class InputMapping
{
    public const SNAPSHOT_TABLE_SUFFIX = 'snapshot';

    private Config $config;

    private Pattern $pattern;

    private TableIdGenerator $tableIdGenerator;

    private string $inputTableName;

    private Table $inputTable;

    private Table $snapshotTable;

    public function __construct(Config $config, Pattern $pattern)
    {
        $this->config = $config;
        $this->pattern = $pattern;
        $this->inputTableName = $pattern->getInputTableName();

        // Parse input mapping and find source table
        $this->parseInputMapping();
    }

    public function toArray(): array
    {
        return array_map(fn(Table $table) => $table->toArray(), $this->getNewMapping());
    }

    public function getNewMapping(): array
    {
        return [$this->getInputTable(), $this->getSnapshotTable()];
    }

    public function getInputTable(): Table
    {
        return $this->inputTable;
    }

    public function getSnapshotTable(): Table
    {
        return $this->snapshotTable;
    }

    /**
     * In input mapping are expected two tables with destination:
     * "input_table", and "current_snapshot", all others are ignored.
     *
     * If only one table is present, it is taken as the "input_table", and destination is modified.
     */
    private function parseInputMapping(): void
    {
        $imTables = $this->config->getInputTables();

        // No table in input mapping
        if (count($imTables) === 0) {
            throw new UserException('Please specify one input table in the input mapping.');
        }

        if (count($imTables) === 1) {
            // One table in input mapping -> it is source table, we rewrite the destination
            $data = $imTables[0];
            $data['destination'] = $this->inputTableName;
            $this->inputTable = $this->createTable($data);
        } else {
            // Multiple tables in input mapping, we need to find source table by "destination" = "input_table"
            $this->inputTable = $this->findSnapshotTable($imTables);
        }

        // Create table id generator from source table
        $this->tableIdGenerator = TableIdGenerator::createFromSourceTable($this->config, $this->inputTable);

        // Generate snapshot table
        $this->generateSnapshotTable();
    }

    private function findSnapshotTable(array $imTables): Table
    {
        $sourceTable = null;
        foreach ($imTables as $data) {
            switch ($data['destination'] ?? null) {
                // Found input table
                case $this->inputTableName:
                    if ($sourceTable) {
                        throw new UserException(sprintf(
                            'Found multiple tables with "destination" = "%s" in input mapping, but only one allowed.',
                            $this->inputTableName
                        ));
                    }
                    $sourceTable = $this->createTable($data);
                    break;

                default:
                    // Other tables are not expected and ignored
                    // Will not be included in the generated input mapping
            }
        }

        // No source table found -> error
        if (!$sourceTable) {
            throw new UserException(sprintf(
                'Found "%d" tables in input mapping, but no source table with "destination" = "%s". ' .
                'Please set the source table in the input mapping.',
                count($imTables),
                $this->inputTableName
            ));
        }

        return $sourceTable;
    }

    private function generateSnapshotTable(): void
    {
        $customSnapshotName = $this->config->getSnapshotTableName();

        if (!empty($customSnapshotName)) {
            // When custom snapshot name is defined, append it directly to the source table name
            $source = $this->tableIdGenerator->generateDirect(
                $customSnapshotName,
                TableIdGenerator::STAGE_OUTPUT
            );
        } else {
            // Default behavior - use hash and standard snapshot suffix
            $source = $this->tableIdGenerator->generate(
                self::SNAPSHOT_TABLE_SUFFIX,
                TableIdGenerator::STAGE_OUTPUT
            );
        }

        $actualName = $this->pattern->getParameters()->getActualName();
        $where_column = $this->config->getUppercaseColumns() ? mb_strtoupper($actualName) : $actualName;

        $data = [
            'source' => $source,
            'destination' => $this->pattern->getSnapshotInputTable(),
            'where_column' => $where_column,
            'where_values' => [str_replace("'", '', $this->pattern->getParameters()->getDeletedFlagValue()[1])],
        ];
        $this->snapshotTable = $this->createTable($data);
    }

    private function createTable(array $data): Table
    {
        return new Table($data, Table::MAPPING_TYPE_INPUT);
    }
}
