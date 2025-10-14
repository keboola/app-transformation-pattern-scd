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
    private const NATIVE_TYPES_FEATURES = ['native-types', 'new-native-types'];

    private Client $client;

    private string $dataDir;

    public function __construct(Client $client, string $dataDir)
    {
        $this->client = $client;
        $this->dataDir = $dataDir;
    }

    public function createSnapshotTable(
        Table $snapshotTable,
        Pattern $pattern
    ): void {
        try {
            if ($this->client->tableExists($snapshotTable->getTableId())) {
                return;
            }
            $this->createBucketIfNotExists($snapshotTable);

            $features = $this->client->verifyToken()['owner']['features'];
            if (count(array_intersect(self::NATIVE_TYPES_FEATURES, $features)) > 0) {
                $this->client->createTableDefinition(
                    $snapshotTable->getBuckedId(),
                    [
                        'name' => $snapshotTable->getTableName(),
                        'primaryKeysNames' => [$pattern->getSnapshotPrimaryKey()],
                        'columns' => $pattern->getSnapshotTypedColumns(),
                    ]
                );
            } else {
                $csvFile = new CsvFile(sprintf('%s/snapshot.csv', $this->dataDir));
                $csvFile->writeRow($pattern->getSnapshotTableHeader());
                $this->client->createTableAsync(
                    $snapshotTable->getBuckedId(),
                    $snapshotTable->getTableName(),
                    $csvFile,
                    ['primaryKey' => $pattern->getSnapshotPrimaryKey()]
                );
            }
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
}
