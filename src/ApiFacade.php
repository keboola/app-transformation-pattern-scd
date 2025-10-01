<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\TransformationPatternScd\Mapping\Table;
use Keboola\TransformationPatternScd\Patterns\Pattern;

class ApiFacade
{
    private Client $client;

    private string $dataDir;

    public function __construct(Client $client, string $dataDir)
    {
        $this->client = $client;
        $this->dataDir = $dataDir;
    }

    public function createSnapshotTable(
        Table $snapshotTable,
        array $inTableDetail,
        Pattern $pattern
    ): void {
        try {
            $this->createBucketIfNotExists($snapshotTable);
            $this->createTableIfNotExists($snapshotTable, $inTableDetail, $pattern);
        } catch (ClientException $e) {
            throw new UserException(
                sprintf(
                    'Cannot create snapshot table "%s": %s',
                    $snapshotTable->getTableId(),
                    $e->getMessage(),
                ),
                0,
                $e
            );
        }
    }

    public function getTable(string $tableId): array
    {
        return $this->client->getTable($tableId);
    }

    private function createBucketIfNotExists(Table $snapshotTable): void
    {
        $bucketId = $snapshotTable->getBuckedId();
        if (!$this->client->bucketExists($bucketId)) {
            // Split bucket id to stage and name
            [$stage, $name] = explode('.', $bucketId, 2);
            // Remove c- from the name start
            $name = (string) preg_replace('~^c-~', '', $name);
            $this->client->createBucket($name, $stage);
        }
    }

    private function createTableIfNotExists(
        Table $snapshotTable,
        array $tableDefinition,
        Pattern $pattern
    ): void {

        if ($this->client->tableExists($snapshotTable->getTableId())) {
            return;
        }
        putenv('KBC_PROJECT_FEATURE_GATES=feature1;new-native-types');

        $featureGates = getenv('KBC_PROJECT_FEATURE_GATES') ?: '';
        $featureGatesArray = array_map('trim', explode(';', $featureGates));

        if (array_intersect(['native-types', 'new-native-types'], $featureGatesArray)) {
            $params = $pattern->getParameters();
            $uppercaseColumns = $params->getUppercaseColumns();

            // prepare columns from the historized table, apply uppercase if needed
            if (isset($tableDefinition['definition'])) {
                $inputColumns = array_map(function ($column) use ($uppercaseColumns) {
                    unset($column['canBeFiltered']);

                    if ($uppercaseColumns) {
                        $column['name'] = strtoupper($column['name']);
                    }

                    return $column;
                }, $tableDefinition['definition']['columns']);
            } else {
                // else create table definition with strings for non-typed tables
                $inputColumns = array_map(function ($column) use ($uppercaseColumns) {
                    if ($uppercaseColumns) {
                        $column = strtoupper($column);
                    }
                    return ['name' => $column, 'definition' => ['type' => 'VARCHAR']];
                }, $tableDefinition['columns']);
            }

            $helperColumnsDetailed = array_map(fn($col) => ['name' => $col], $pattern->getSnapshotSpecialColumns());

            $dateType = $params->useDatetime() ? 'DATETIME' : 'DATE';

            $deletedFlagValue = $params->getDeletedFlagValue();

            $lenToUse = max(
                mb_strlen(str_replace("'", '', (string) $deletedFlagValue[0])),
                mb_strlen(str_replace("'", '', (string) $deletedFlagValue[1]))
            );

            $dataColsCount = 2;
            if ($params->getScdType() === 'scd4') {
                $dataColsCount = 1;
            }

            for ($i = 0; $i < $dataColsCount; $i++) {
                $helperColumnsDetailed[$i]['definition'] = ['type' => $dateType];
            }

            for ($i = $dataColsCount; $i < count($helperColumnsDetailed); $i++) {
                if (in_array('0', $deletedFlagValue, true) && in_array('1', $deletedFlagValue, true)) {
                    $helperColumnsDetailed[$i]['definition'] = ['type' => 'NUMERIC', 'length' => 1];
                } else {
                    $helperColumnsDetailed[$i]['definition'] = ['type'   => 'VARCHAR', 'length' => $lenToUse];
                }
            }

            $this->client->createTableDefinition(
                $snapshotTable->getBuckedId(),
                [
                    'name' => $snapshotTable->getTableName(),
                    'columns' => array_merge($inputColumns, $helperColumnsDetailed),
                ]
            );
        } else {
            $snapshotPrimaryKey = $pattern->getSnapshotPrimaryKey();

            $header = $pattern->getSnapshotTableHeader();

            $csvFile = new CsvFile(sprintf('%s/snapshot.csv', $this->dataDir));
            $csvFile->writeRow($header);
            $this->client->createTableAsync(
                $snapshotTable->getBuckedId(),
                $snapshotTable->getTableName(),
                $csvFile,
                ['primaryKey' => $snapshotPrimaryKey]
            );
        }
    }
}
