<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\TransformationPatternScd\Mapping\Table;

class TableIdGenerator
{
    public const HASH_LENGTH = 10;

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

    public function __construct(Config $config, string $bucketId, string $sourceTableName)
    {
        // We add hash to table name,
        // ... so if the user changes the configuration, there will be no problem with incompatible tables.
        $this->configHash = substr(md5(serialize($config->getData()['parameters'])), 0, self::HASH_LENGTH);
        $this->bucketId = $bucketId;
        $this->sourceTableName = $sourceTableName;
    }

    public function generate(string $name): string
    {
        return sprintf('%s.%s_%s_%s', $this->bucketId, $this->sourceTableName, $this->configHash, $name);
    }
}
