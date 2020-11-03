<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
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
        $application = new Application($config, $this->getLogger(), $this->getDataDir());

        return [
            'result' => $application->generateConfig(),
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

    protected function loadConfig(): void
    {
        try {
            parent::loadConfig();
        } catch (UserException $e) {
            // Empty configuration -> convert to user friendly message
            if (strpos($e->getMessage(), ' at path "root.parameters" must be configured') !== false) {
                throw new UserException(
                    'Did you forget to save the configuration? ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            throw $e;
        }
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
