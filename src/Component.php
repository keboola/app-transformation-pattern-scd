<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\StorageApi\Client;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;

class Component extends BaseComponent
{
    private const ACTION_RUN = 'run';

    private const ACTION_GENERATE = 'generate';

    protected function run(): void
    {
        throw new UserException('Action "run" does not implemented');
    }

    protected function generate(): array
    {
        /** @var Config $config */
        $config = $this->getConfig();

        $application = new Application($config, $this->getLogger());

        $originalInputMapping = array_values(array_filter($config->getInputTables(), function ($v) {
            return !in_array($v['destination'], ['curr_snapshot', 'in_table']);
        }));

        if ($originalInputMapping) {
            $destinationTableName = $application->createDestinationTable(
                new Client(
                    [
                        'url' => $config->getStorageApiUrl(),
                        'token' => $config->getStorageApiToken(),
                    ]
                ),
                $this->getDataDir(),
                $originalInputMapping[0]['source']
            );
        }

        return [
            'result' => $application->generate(),
        ];
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_GENERATE => 'generate',
        ];
    }

    protected function getConfigDefinitionClass(): string
    {
        $action = $this->getRawConfig()['action'] ?? self::ACTION_RUN;
        switch ($action) {
            case self::ACTION_GENERATE:
                return GenerateDefinition::class;
            default:
                throw new UserException(sprintf('Unexpected action "%s"', $action));
        }
    }
}
