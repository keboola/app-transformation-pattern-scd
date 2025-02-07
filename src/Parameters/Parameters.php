<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Parameters;

class Parameters
{
    public const BACKEND_SNOWFLAKE = 'snowflake';
    public const BACKEND_SYNAPSE = 'synapse';

    private string $backend;

    private array $primaryKey;

    private array $monitoredParameters;

    private string $timezone;

    private bool $deletedFlag;

    private bool $useDatetimeValue;

    private bool $keepDeletedActiveValue;

    private string $startDateName;

    private string $endDateName;

    public function __construct(
        string $backend,
        array $primaryKey,
        array $monitoredParameters,
        string $timezone,
        bool $deletedFlag,
        bool $useDatetime,
        bool $keepDeletedActive,
        string $startDateName,
        string $endDateName
    ) {
        $this->backend = $backend;
        $this->primaryKey = $primaryKey;
        $this->monitoredParameters = $monitoredParameters;
        $this->timezone = $timezone;
        $this->deletedFlag = $deletedFlag;
        $this->useDatetimeValue = $useDatetime;
        $this->keepDeletedActiveValue = $keepDeletedActive;
        $this->startDateName = $startDateName;
        $this->endDateName = $endDateName;
    }

    public function getBackend(): string
    {
        return $this->backend;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    public function getMonitoredParameters(): array
    {
        return $this->monitoredParameters;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function hasDeletedFlag(): bool
    {
        return $this->deletedFlag;
    }

    public function useDatetime(): bool
    {
        return $this->useDatetimeValue;
    }

    public function keepDeleteActive(): bool
    {
        return $this->keepDeletedActiveValue;
    }

    public function getStartDateName(): string
    {
        return $this->startDateName;
    }

    public function getEndDateName(): string
    {
        return $this->endDateName;
    }
}
