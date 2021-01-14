<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\TransformationPatternScd\Mapping\Table;

class ApiFacade
{
    private Config $config;

    private Client $client;

    private string $dataDir;

    public function __construct(Config $config, Client $client, string $dataDir)
    {
        $this->config = $config;
        $this->client = $client;
        $this->dataDir = $dataDir;
    }

    public function createSnapshotTable(Table $snapshotTable, array $header, string $primaryKey): void
    {
        try {
            $this->createBucketIfNotExists($snapshotTable);
            $this->createTableIfNotExists($snapshotTable, $header, $primaryKey);
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

    private function createTableIfNotExists(Table $snapshotTable, array $header, string $primaryKey): void
    {
        if (!$this->client->tableExists($snapshotTable->getTableId())) {
            $csvFile = new CsvFile(sprintf('%s/snapshot.csv', $this->dataDir));
            $csvFile->writeRow($header);
            $this->client->createTableAsync(
                $snapshotTable->getBuckedId(),
                $snapshotTable->getTableName(),
                $csvFile,
                ['primaryKey'=> $primaryKey]
            );
        }
    }
}
