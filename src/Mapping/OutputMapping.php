<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Application;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;

class OutputMapping
{
    public const SNAPSHOT_TABLE_SOURCE = 'final_snapshot';

    private Config $config;

    private InputMapping $inputMapping;

    /** @var Table[] */
    private array $tables = [];

    public function __construct(Config $config, InputMapping $inputMapping)
    {
        $this->config = $config;
        $this->inputMapping = $inputMapping;
        $this->generateOutputMapping();
    }

    public function toArray(): array
    {
        return array_map(fn(Table $table) => $table->toArray(), $this->getTables());
    }

    public function getTables(): array
    {
        return $this->tables;
    }

    private function generateOutputMapping(): void
    {
        // Output mapping for snapshot table
        $snapshotInputMapping = $this->inputMapping->getSnapshotTable();
        $this->tables[] = $this->createTable([
            'source' => self::SNAPSHOT_TABLE_SOURCE,
            'destination' => $snapshotInputMapping->getSource(),
            'primaryKey' => [Application::COL_SNAP_PK],
            'incremental' => true,
        ]);

        // Output mapping of aux tables -> for debug
        $sourceInputMapping = $this->inputMapping->getSourceTable();
        foreach ($this->getOutputTablesList() as $tableName) {
            $this->tables[] = $this->createTable([
                'source' => $tableName,
                'destination' => sprintf(
                    '%s.%s_%s',
                    $snapshotInputMapping->getBuckedId(),
                    $tableName,
                    $sourceInputMapping->getTableName()
                ),
            ]);
        }
    }

    private function getOutputTablesList(): array
    {
        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                return [
                    'changed_records_snapshot',
                    'deleted_records_snapshot',
                    'updated_snapshots',
                ];
            case GenerateDefinition::SCD_TYPE_4:
                return [
                    'last_curr_records',
                    'deleted_records_snapshot',
                ];
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }
    }

    private function createTable(array $data): Table
    {
        return new Table($data, Table::MAPPING_TYPE_OUTPUT);
    }
}
