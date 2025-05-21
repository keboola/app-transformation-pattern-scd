<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use InvalidArgumentException;
use Keboola\TransformationPatternScd\Mapping\Table;

class TableIdGenerator
{
    public const STAGE_INPUT = 'in';
    public const STAGE_OUTPUT = 'out';

    public const HASH_LENGTH = 6;

    private string $configHash;

    private string $bucketId;

    private string $sourceTableName;

    public static function createFromSourceTable(Config $config, Table $sourceTable): self
    {
        return new self(
            $config,
            $sourceTable->getBuckedId(),
            $sourceTable->getTableName(),
        );
    }

    public function __construct(Config $config, string $fullBucketId, string $sourceTableName)
    {
        [$stage, $bucketId] = explode('.', $fullBucketId, 2);
        if ($stage !== 'in' && $stage !== 'out') {
            throw new InvalidArgumentException(sprintf(
                'Expected bucket from IN/OUT stage, given "%s".',
                $fullBucketId
            ));
        }

        // We add hash to table name,
        // ... so if the user changes the configuration, there will be no problem with incompatible tables.
        $this->configHash = substr(md5(serialize($config->getData()['parameters'])), 0, self::HASH_LENGTH);
        $this->bucketId = $bucketId;
        $this->sourceTableName = $sourceTableName;
    }

    public function generate(string $name, string $stage): string
    {
        return sprintf('%s.%s_%s_%s', $this->getBucketId($stage), $this->sourceTableName, $this->configHash, $name);
    }

    /**
     * Generate a table name by directly appending a suffix to the source table name without hashes.
     */
    public function generateDirect(string $suffix, string $stage): string
    {
        return sprintf('%s.%s%s', $this->getBucketId($stage), $this->sourceTableName, $suffix);
    }

    private function getBucketId(string $stage): string
    {
        switch ($stage) {
            case self::STAGE_INPUT:
                return 'in.' . $this->bucketId;

            case self::STAGE_OUTPUT:
                return 'out.' . $this->bucketId;

            default:
                throw new \InvalidArgumentException(sprintf('Unexpected stage "%s".', $stage));
        }
    }
}
