<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\StorageApi\Client;
use Keboola\TransformationPatternScd\Parameters\ParametersFactory;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\Mapping\MappingManager;
use Keboola\TransformationPatternScd\Patterns\PatternFactory;
use Psr\Log\LoggerInterface;

class Application
{
    public const SNOWFLAKE_TRANS_COMPONENT = 'keboola.snowflake-transformation';

    private string $dataDir;

    private Config $config;

    private LoggerInterface $logger;

    private ApiFacade $apiFacade;

    private PatternFactory $patternFactory;

    private Pattern $pattern;

    private MappingManager $mappingManager;

    private StorageGenerator $storageGenerator;

    private BlocksGenerator $blocksGenerator;

    public function __construct(string $dataDir, Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->dataDir = $dataDir;
        $apiClient = new Client(['url' => $config->getStorageApiUrl(), 'token' => $config->getStorageApiToken()]);
        $this->apiFacade = new ApiFacade($apiClient, $dataDir);

        // Create input table resolver
        $inputTableResolver = new InputTableResolver($this->config, $this->apiFacade);

        // Create parameters
        $parametersFactory = new ParametersFactory($this->config, $inputTableResolver);
        $parameters = $parametersFactory->create();

        // Create pattern with parameters
        $this->patternFactory = new PatternFactory($this->config->getScdType());
        $this->pattern = $this->patternFactory->create();
        $this->pattern->setParameters($parameters);

        // Create mapping manager with initialized pattern
        $this->mappingManager = new MappingManager($this->config, $this->pattern);
        $this->storageGenerator = new StorageGenerator($this->mappingManager);
        $this->blocksGenerator = new BlocksGenerator();
    }

    public function generateConfig(): array
    {
        // Create snapshot table by API
        $this->apiFacade->createSnapshotTable(
            $this->mappingManager->getInputMapping()->getSnapshotTable(),
            $this->pattern->getSnapshotTableHeader(),
            $this->pattern->getSnapshotPrimaryKey(),
        );

        return [
            'storage' => $this->storageGenerator->generate(),
            'parameters' => $this->blocksGenerator->generate($this->pattern->render()),
        ];
    }
}
