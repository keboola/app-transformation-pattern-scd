<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\TransformationPatternScd\Mapping\MappingManager;
use Keboola\TransformationPatternScd\Patterns\Pattern;

class ApiFacade
{
    private Config $config;

    private Pattern $pattern;

    private MappingManager $mappingManager;

    private string $dataDir;

    private Client $client;

    public function __construct(Config $config, Pattern $pattern, MappingManager $mappingManager, string $dataDir)
    {
        $this->config = $config;
        $this->pattern = $pattern;
        $this->mappingManager = $mappingManager;
        $this->dataDir = $dataDir;
        $this->client = new Client([
            'url' => $this->config->getStorageApiUrl(),
            'token' => $this->config->getStorageApiToken(),
        ]);
    }

    public function createSnapshotTable(): void
    {
        $csvFile = new CsvFile(sprintf('%s/snapshot.csv', $this->dataDir));
        $csvFile->writeRow($this->pattern->getSnapshotTableHeader());

        $snapshotTable = $this->mappingManager->getInputMapping()->getSnapshotTable();

        try {
            $bucketId = $snapshotTable->getBuckedId();
            if (!$this->client->bucketExists($bucketId)) {
                // Split bucket id to stage and name
                [$stage, $name] = explode('.', $bucketId, 2);
                // Remove c- from the name start
                $name = (string) preg_replace('~^c-~', '', $name);
                $this->client->createBucket($name, $stage);
            }

            $this->client->createTable(
                $bucketId,
                $snapshotTable->getTableName(),
                $csvFile,
                ['primaryKey'=> $this->pattern->getSnapshotPrimaryKey()]
            );
        } catch (ClientException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }
}
