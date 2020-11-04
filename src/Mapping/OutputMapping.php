<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Application;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\TableIdGenerator;

class OutputMapping
{
    public const INCLUDE_AUX_TABLES_IN_OUTPUT_MAPPING = false;

    private Config $config;

    private Pattern $pattern;

    private InputMapping $inputMapping;

    private TableIdGenerator $tableIdGenerator;

    /** @var Table[] */
    private array $newMapping = [];

    public function __construct(Config $config, Pattern $pattern, InputMapping $inputMapping)
    {
        $this->config = $config;
        $this->pattern = $pattern;
        $this->inputMapping = $inputMapping;
        $this->tableIdGenerator = TableIdGenerator::createFromSourceTable($config, $inputMapping->getInputTable());
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
            'source' => $this->pattern->getSnapshotOutputTable(),
            'destination' => $snapshotInputMapping->getSource(),
            'primary_key' => [$this->pattern->getSnapshotPrimaryKey()],
            'incremental' => true,
        ]);

        // Output mapping of aux tables -> for debug
        if (self::INCLUDE_AUX_TABLES_IN_OUTPUT_MAPPING) {
            foreach ($this->getAuxiliaryTables() as $tableName) {
                $this->newMapping[] = $this->createTable([
                    'source' => $tableName,
                    'destination' => $this->tableIdGenerator->generate(
                        $tableName,
                        TableIdGenerator::STAGE_OUTPUT
                    ),
                ]);
            }
        }
    }

    private function getAuxiliaryTables(): array
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
