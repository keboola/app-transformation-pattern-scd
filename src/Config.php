<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\Config\BaseConfig;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\Exception\ApplicationException;

class Config extends BaseConfig
{
    public function getStorageApiToken(): string
    {
        $token = getenv('KBC_TOKEN');
        if (!$token) {
            throw new ApplicationException('KBC_TOKEN environment variable must be set');
        }
        return $token;
    }

    public function getStorageApiUrl(): string
    {
        $url = getenv('KBC_URL');
        if (!$url) {
            throw new ApplicationException('KBC_URL environment variable must be set');
        }
        return $url;
    }

    public function getComponentId(): string
    {
        // Returns the ID of the component for which the code is generated.
        // Eg. "keboola.snowflake-transformation"
        return $this->getValue(['parameters', '_componentId']);
    }

    public function getScdType(): string
    {
        return $this->getValue(['parameters', 'scd_type']);
    }

    public function getIncludedParametersInput(): array
    {
        $monitoredParameters = $this->getValue(['parameters', 'monitored_parameters']);
        $monitoredParameters = array_map(function ($v) {
            return trim($v);
        }, explode(',', $monitoredParameters));

        return $monitoredParameters;
    }

    public function getPrimaryKeyInput(): array
    {
        $primaryKeys = $this->getValue(['parameters', 'primary_key']);
        $primaryKeys = array_map(function ($v) {
            return trim($v);
        }, explode(',', $primaryKeys));

        return $primaryKeys;
    }

    public function hasDeletedFlag(): bool
    {
        return (bool) $this->getValue(['parameters', 'deleted_flag']);
    }

    public function useDatetime(): bool
    {
        return (bool) $this->getValue(['parameters', 'use_datetime']);
    }

    public function keepDeleteActive(): bool
    {
        return (bool) $this->getValue(['parameters', 'keep_del_active']);
    }

    public function getTimezone(): string
    {
        return $this->getValue(['parameters', 'timezone']);
    }

    public function getStartDateName(): string
    {
        return $this->getValue(['parameters', 'start_date_name']);
    }

    public function getEndDateName(): string
    {
        return $this->getValue(['parameters', 'end_date_name']);
    }

    public function getActualName(): string
    {
        return $this->getValue(['parameters', 'actual_name']);
    }

    public function getIsDeletedName(): string
    {
        return $this->getValue(['parameters', 'is_deleted_name']);
    }

    public function getDeletedFlagValue(): string
    {
        return $this->getValue(['parameters', 'deleted_flag_value']);
    }

    public function getEndDateValue(): string
    {
        return $this->getValue(['parameters', 'end_date_value']);
    }

    public function getCurrentTimestampMinusOne(): bool
    {
        return (bool) $this->getValue(['parameters', 'current_timestamp_minus_one']);
    }

    public function getUppercaseColumns(): bool
    {
        return (bool) $this->getValue(['parameters', 'uppercase_columns']);
    }

    public function getEffectiveDateAdjustment(): int
    {
        return (int) $this->getValue(['parameters', 'effective_date_adjustment']);
    }
}
