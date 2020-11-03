<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Mapping;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Exception\ApplicationException;

class Table
{
    public const MAPPING_TYPE_INPUT = 'input';
    public const MAPPING_TYPE_OUTPUT = 'output';

    private array $data;

    private string $mappingType;

    public function __construct(array $data, string $mappingType)
    {
        if (!in_array($mappingType, [self::MAPPING_TYPE_INPUT, self::MAPPING_TYPE_OUTPUT], true)) {
            throw new ApplicationException(sprintf('Unexpected mapping type "%s".', $mappingType));
        }

        $this->data = $data;
        $this->mappingType = $mappingType;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function getMappingType(): string
    {
        return $this->mappingType;
    }

    public function getSource(): string
    {
        return $this->data['source'];
    }

    public function getDestination(): string
    {
        return $this->data['destination'];
    }

    public function getTableId(): string
    {
        switch ($this->mappingType) {
            case self::MAPPING_TYPE_INPUT:
                return $this->getSource();

            case self::MAPPING_TYPE_OUTPUT:
                return $this->getDestination();

            default:
                throw new ApplicationException(sprintf('Unexpected mapping type "%s".', $this->mappingType));
        }
    }

    public function getBuckedId(): string
    {
        return $this->getTableIdParts()[0];
    }

    public function getTableName(): string
    {
        return $this->getTableIdParts()[1];
    }

    private function getTableIdParts(): array
    {
        $tableId = $this->getTableId();

        // Split table id: prefix (all before last ".") AND table name (all after last "." character)
        if (!preg_match('~^(.+)\.([^.]+)~', $tableId, $matches)) {
            throw new UserException(sprintf('Unexpected format of the table id "%s".', $tableId));
        }

        [$_, $bucketId, $tableName] = $matches;
        return [$bucketId, $tableName];
    }
}
