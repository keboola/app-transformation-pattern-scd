<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Application;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\TableIdGenerator;

class OutputMapping
{
    public const SNAPSHOT_TABLE_SOURCE = 'new_snapshot';

    private Config $config;

    private InputMapping $inputMapping;

    private TableIdGenerator $tableIdGenerator;

    /** @var Table[] */
    private array $newMapping = [];

    public function __construct(Config $config, InputMapping $inputMapping)
    {
        $this->config = $config;
        $this->inputMapping = $inputMapping;
        $this->tableIdGenerator = TableIdGenerator::createFromSourceTable($config, $inputMapping->getSourceTable());
        $this->generateOutputMapping();
    }

    public function toArray(): array
    {
        return array_map(fn(Table $table) => $table->toArray(), $this->getNewMapping());
    }

    public function getNewMapping(): array
    {
        return $this->newMapping;
    }

    private function generateOutputMapping(): void
    {
        // Output mapping for snapshot table
        $snapshotInputMapping = $this->inputMapping->getSnapshotTable();
        $this->newMapping[] = $this->createTable([
            'source' => self::SNAPSHOT_TABLE_SOURCE,
            'destination' => $snapshotInputMapping->getSource(),
            'primary_key' => [Application::COL_SNAP_PK],
            'incremental' => true,
        ]);

        // Output mapping of aux tables -> for debug
        foreach ($this->getOutputTablesList() as $tableName) {
            $this->newMapping[] = $this->createTable([
                'source' => $tableName,
                'destination' => $this->tableIdGenerator->generate(
                    $tableName,
                    TableIdGenerator::STAGE_OUTPUT
                ),
            ]);
        }
    }

    private function getOutputTablesList(): array
    {
        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                return [
                    'changed_records',
                    'deleted_records',
                    'updated_snapshots',
                ];
            case GenerateDefinition::SCD_TYPE_4:
                return [
                    'last_current_records',
                    'deleted_records',
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
