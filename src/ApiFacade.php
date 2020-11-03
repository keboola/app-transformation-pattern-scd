<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\Mapping\MappingManager;

class ApiFacade
{
    private Config $config;

    private MappingManager $mappingManager;

    private string $dataDir;

    private Client $client;

    public function __construct(Config $config, MappingManager $mappingManager, string $dataDir)
    {
        $this->config = $config;
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
        $csvFile->writeRow($this->getSnapshotTableHeader());

        $snapshotTable = $this->mappingManager->getInputMapping()->getSnapshotTable();

        try {
            $this->client->createTable(
                $snapshotTable->getBuckedId(),
                $snapshotTable->getTableName(),
                $csvFile,
                ['primaryKey'=> Application::COL_SNAP_PK]
            );
        } catch (ClientException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }
    }

    private function getSnapshotTableHeader(): array
    {
        $header = [];
        $header[] = Application::COL_SNAP_PK;
        $header = array_merge($header, $this->config->getPrimaryKey(), $this->config->getMonitoredParameters());

        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                $header[] = Application::COL_START_DATE;
                $header[] = Application::COL_END_DATE;
                break;
            case GenerateDefinition::SCD_TYPE_4:
                $header[] = Application::COL_SNAP_DATE;
                break;
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }

        $header[] = Application::COL_ACTUAL;

        if ($this->config->hasDeletedFlag()) {
            $header[] = Application::COL_IS_DELETED;
        }

        array_walk($header, function (&$v): void {
            $v = mb_strtolower($v);
        });

        return $header;
    }
}
