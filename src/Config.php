<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function getScdType(): string
    {
        return $this->getValue(['parameters', 'scd_type']);
    }

    public function getMonitoredParameters(): array
    {
        $monitoredParameters = $this->getValue(['parameters', 'monitored_parameters']);
        $monitoredParameters = array_map(function ($v) {
            return trim($v);
        }, explode(',', $monitoredParameters));

        return $monitoredParameters;
    }

    public function getPrimaryKey(): array
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
}
