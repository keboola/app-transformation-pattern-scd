<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Parameters;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\ApiFacade;
use Keboola\TransformationPatternScd\Application;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Mapping\MappingManager;

class ParametersFactory
{
    private Config $config;

    private ApiFacade $apiFacade;

    private MappingManager $mappingManager;

    private ?array $inputTableDetail = null;

    public function __construct(Config $config, ApiFacade $apiFacade, MappingManager $mappingManager)
    {
        $this->config = $config;
        $this->apiFacade = $apiFacade;
        $this->mappingManager = $mappingManager;
    }

    public function create(): Parameters
    {
        return new Parameters(
            $this->getBackend(),
            $this->getPrimaryKey(),
            $this->getMonitoredParameters(),
            $this->config->getTimezone(),
            $this->config->hasDeletedFlag(),
            $this->config->useDatetime(),
            $this->config->keepDeleteActive(),
            $this->config->getStartDateName(),
            $this->config->getEndDateName(),
            $this->config->getActualName(),
            $this->config->getIsDeletedName(),
            $this->config->getDeletedFlagValue(),
            $this->config->getEndDateValue(),
            $this->config->getCurrentTimestampMinusOne(),
            $this->config->getUppercaseColumns(),
            $this->config->getEffectiveDateAdjustment(),
            $this->config->getSnapshotTableName()
        );
    }

    private function getBackend(): string
    {
        switch ($this->config->getComponentId()) {
            case Application::SNOWFLAKE_TRANS_COMPONENT:
                return Parameters::BACKEND_SNOWFLAKE;
            default:
                throw new UserException(sprintf(
                    'The SCD code pattern is not compatible with component "%s".',
                    $this->config->getComponentId()
                ));
        }
    }

    private function getPrimaryKey(): array
    {
        $configColumns = $this->config->getPrimaryKeyInput();
        $storageColumns = $this->getInputTableColumns();
        $missingColumns = array_diff($configColumns, $storageColumns);
        if ($missingColumns) {
            throw new UserException(sprintf(
                'Primary key "%s" not found in the input table "%s".',
                implode('", "', $missingColumns),
                $this->getInputTableId()
            ));
        }

        return $configColumns;
    }

    private function getMonitoredParameters(): array
    {
        $configColumns = $this->config->getIncludedParametersInput();
        $storageColumns = $this->getInputTableColumns();
        $missingColumns = array_diff($configColumns, $storageColumns);
        if ($missingColumns) {
            throw new UserException(sprintf(
                'Monitored parameter "%s" not found in the input table "%s".',
                implode('", "', $missingColumns),
                $this->getInputTableId()
            ));
        }

        return $configColumns;
    }

    private function getInputTableColumns(): array
    {
        return $this->getInputTableDetail()['columns'];
    }

    private function getInputTableDetail(): array
    {
        if (!$this->inputTableDetail) {
            $this->inputTableDetail =
                $this->apiFacade->getTable($this->getInputTableId());
        }

        return $this->inputTableDetail;
    }

    private function getInputTableId(): string
    {
        return $this->mappingManager->getInputMapping()->getInputTable()->getSource();
    }
}
