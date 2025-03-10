<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\TableIdGenerator;

class OutputMapping
{
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
            'primary_key' => [
                // Cannot be load from parameters, because they are initialized later 'setParameters'
                $this->config->getUppercaseColumns()
                    ? mb_strtoupper(Pattern::COLUMN_SNAPSHOT_PK)
                    : Pattern::COLUMN_SNAPSHOT_PK,
            ],
            'incremental' => true,
        ]);
    }

    private function createTable(array $data): Table
    {
        return new Table($data, Table::MAPPING_TYPE_OUTPUT);
    }
}
