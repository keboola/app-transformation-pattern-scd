<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Parameters;

class Parameters
{
    public const BACKEND_SNOWFLAKE = 'snowflake';

    private string $backend;

    private array $primaryKey;

    private array $monitoredParameters;

    private string $timezone;

    private bool $deletedFlag;

    private bool $useDatetimeValue;

    private bool $keepDeletedActiveValue;

    private string $startDateName;

    private string $endDateName;

    private string $actualName;

    private string $isDeletedName;

    private string $deletedFlagValue;

    private string $endDateValue;

    private bool $currentTimestampMinusOne;

    private bool $uppercaseColumns;

    public function __construct(
        string $backend,
        array $primaryKey,
        array $monitoredParameters,
        string $timezone,
        bool $deletedFlag,
        bool $useDatetime,
        bool $keepDeletedActive,
        string $startDateName,
        string $endDateName,
        string $actualName,
        string $isDeletedName,
        string $deletedFlagValue,
        string $endDateValue,
        bool $currentTimestampMinusOne,
        bool $uppercaseColumns
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
        $this->actualName = $actualName;
        $this->isDeletedName = $isDeletedName;
        $this->deletedFlagValue = $deletedFlagValue;
        $this->endDateValue = $endDateValue;
        $this->currentTimestampMinusOne = $currentTimestampMinusOne;
        $this->uppercaseColumns = $uppercaseColumns;
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

    public function getActualName(): string
    {
        return $this->actualName;
    }

    public function getIsDeletedName(): string
    {
        return $this->isDeletedName;
    }

    public function getDeletedFlagValue(): string
    {
        return $this->deletedFlagValue;
    }

    public function getEndDateValue(): string
    {
        return $this->endDateValue;
    }

    public function getCurrentTimestampMinusOne(): bool
    {
        return $this->currentTimestampMinusOne;
    }

    public function getUppercaseColumns(): bool
    {
        return $this->uppercaseColumns;
    }
}
