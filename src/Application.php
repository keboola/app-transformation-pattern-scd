<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\TransformationPatternScd\Parameters\ParametersFactory;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use Keboola\TransformationPatternScd\Mapping\MappingManager;
use Keboola\TransformationPatternScd\Patterns\PatternFactory;
use Psr\Log\LoggerInterface;

class Application
{
    public const SNOWFLAKE_TRANS_COMPONENT = 'keboola.snowflake-transformation';
    public const SYNAPSE_TRANS_COMPONENT = 'keboola.synapse-transformation';

    private string $dataDir;

    private Config $config;

    private LoggerInterface $logger;

    private ApiFacade $apiFacade;

    private PatternFactory $patternFactory;

    private Pattern $pattern;

    private MappingManager $mappingManager;

    private StorageGenerator $storageGenerator;

    private BlocksGenerator $blocksGenerator;

    private ParametersFactory $parametersFactory;

    public function __construct(string $dataDir, Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->dataDir = $dataDir;
        $apiClientFactory = new ApiClientFactory($this->config);
        $apiClient = $apiClientFactory->create();
        $this->apiFacade = new ApiFacade($this->config, $apiClient, $dataDir);
        $this->patternFactory = new PatternFactory($this->config->getScdType());
        $this->pattern = $this->patternFactory->create();
        $this->mappingManager = new MappingManager($this->config, $this->pattern);
        $this->storageGenerator = new StorageGenerator($this->mappingManager);
        $this->blocksGenerator = new BlocksGenerator();
        $this->parametersFactory = new ParametersFactory($this->config, $this->apiFacade, $this->mappingManager);
    }

    public function generateConfig(): array
    {
        // Create parameters
        $parameters = $this->parametersFactory->create();
        $this->pattern->setParameters($parameters);

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
