<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\Mapping\MappingManager;
use Psr\Log\LoggerInterface;

class Application
{
    public const COL_SNAP_PK = 'snap_pk';
    public const COL_START_DATE = 'start_date';
    public const COL_END_DATE = 'end_date';
    public const COL_ACTUAL = 'actual';
    public const COL_IS_DELETED = 'is_deleted';
    public const COL_SNAP_DATE = 'snapshot_date';

    private Config $config;

    private LoggerInterface $logger;

    private string $dataDir;

    private MappingManager $mappingManager;

    private StorageGenerator $storageGenerator;

    private ParametersGenerator $parametersGenerator;

    private ApiFacade $apiFacade;

    public function __construct(Config $config, LoggerInterface $logger, string $dataDir)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->dataDir = $dataDir;
        $this->mappingManager = new MappingManager($this->config);
        $this->storageGenerator = new StorageGenerator($this->mappingManager);
        $this->parametersGenerator = new ParametersGenerator();
        $this->apiFacade = new ApiFacade($this->config, $this->mappingManager, $dataDir);
    }

    public function generateConfig(): array
    {
        $this->validateComponentId();
        $this->apiFacade->createSnapshotTable();
        return [
            'storage' => $this->storageGenerator->generate(),
            'parameters' => $this->parametersGenerator->generate($this->generateSqlCode()),
        ];
    }

    private function validateComponentId(): void
    {
        switch ($this->config->getComponentId()) {
            case 'keboola.snowflake-transformation':
            case 'keboola.synapse-transformation':
                // OK
                return;

            default:
                throw new UserException(sprintf(
                    'The SCD code pattern is not compatible with component "%s".',
                    $this->config->getComponentId()
                ));
        }
    }

    private function generateSqlCode(): string
    {
        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                return $this->generateScd2Type();
            case GenerateDefinition::SCD_TYPE_4:
                return $this->generateScd4Type();
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }
    }

    private function generateScd2Type(): string
    {
        $template = require __DIR__ . '/SqlTemplates/scd2.php';

        $placeholders = $this->getPlaceholders(
            [
                self::COL_START_DATE,
                self::COL_END_DATE,
                self::COL_ACTUAL,
            ],
            [self::COL_START_DATE]
        );

        if (!$this->config->keepDeleteActive()) {
            $placeholders['actual_deleted_value'] = 0;
            $placeholders['actual_deleted_timestamp'] = $placeholders['curr_date_value'];
        } else {
            $placeholders['actual_deleted_value'] = 1;
            $placeholders['actual_deleted_timestamp'] = $this->quoteValue('9999-12-31 00:00:00');
        }

        return str_ireplace($this->getPlaceholdersName($placeholders), $placeholders, $template);
    }

    private function generateScd4Type(): string
    {
        $template = require __DIR__ . '/SqlTemplates/scd4.php';

        $placeholders = $this->getPlaceholders(
            [
                self::COL_SNAP_DATE,
                self::COL_ACTUAL,
            ],
            [self::COL_SNAP_DATE]
        );

        // added deleted flag
        $placeholders['is_deleted_flag'] = '';
        if ($this->config->hasDeletedFlag()) {
            $placeholders['is_deleted_flag'] = sprintf(', %s', $this->quote(self::COL_IS_DELETED));
        }

        // if deleted add union
        $placeholders['deleted_snap_query'] = '';
        if ($this->config->keepDeleteActive()) {
            $deletedRecordsQuery = <<< SQL
UNION
    SELECT \${snap_primary_key_lower}
              ,\${snap_table_cols}
              ,\${snap_default_cols}
    FROM deleted_records_snapshot
SQL;
            $placeholders['deleted_snap_query'] = str_ireplace(
                $this->getPlaceholdersName($placeholders),
                $placeholders,
                $deletedRecordsQuery
            );
        }

        return str_ireplace($this->getPlaceholdersName($placeholders), $placeholders, $template);
    }

    private function getPlaceholders(array $snapDefaultColumns, array $snapDefaultPrimaryKeys): array
    {
        $placeholders = [];

        $columns = array_merge($this->config->getPrimaryKey(), $this->config->getMonitoredParameters());

        $placeholders['input_table_cols'] = implode(', ', array_map(function (string $v) {
            return $this->quote($v);
        }, $columns));

        $placeholders['input_table_cols_w_alias'] = implode(', ', array_map(function (string $v) {
            return 'input.' . $this->quote($v);
        }, $columns));

        $placeholders['snap_table_cols'] = implode(', ', array_map(function (string $v) {
            return $this->quote(strtolower($v));
        }, $columns));

        $placeholders['snap_table_cols_w_alias'] = implode(', ', array_map(function (string $v) {
            return 'snap.' . $this->quote(strtolower($v));
        }, $columns));

        if ($this->config->hasDeletedFlag()) {
            $snapDefaultColumns[] = self::COL_IS_DELETED;
        }
        $placeholders['snap_default_cols'] = implode(', ', array_map(function ($v) {
            return $this->quote($v);
        }, $snapDefaultColumns));

        $currentDateValue = '$CURR_DATE_TXT';
        if ($this->config->useDatetime()) {
            $currentDateValue = '$CURR_TIMESTAMP_TXT';
        }
        $placeholders['curr_date_value'] = $currentDateValue;

        $placeholders['input_random_col'] = $this->quote($this->config->getPrimaryKey()[0]);

        $placeholders['snap_input_join_condition'] = implode(' AND ', array_map(function ($v) {
            return sprintf('snap.%s=input.%s', $this->quote(strtolower($v)), $this->quote($v));
        }, $this->config->getPrimaryKey()));

        $snapPrimaryKey = array_merge($this->config->getPrimaryKey(), $snapDefaultPrimaryKeys);
        $placeholders['snap_primary_key_lower'] = implode('|| \'|\' ||', array_map(function ($v) {
            return $this->quote(strtolower($v));
        }, $snapPrimaryKey));
        $placeholders['snap_primary_key_lower'] .= sprintf(' AS %s', $this->quote(self::COL_SNAP_PK));

        $placeholders['snap_primary_key'] = implode('|| \'|\' ||', array_map(function ($v) {
            return $this->quote($v);
        }, $snapPrimaryKey));
        $placeholders['snap_primary_key'] .= sprintf(' AS %s', $this->quote(self::COL_SNAP_PK));

        $placeholders['timezone'] = $this->config->getTimezone();

        return $placeholders;
    }

    private function quote(string $v): string
    {
        return sprintf('"%s"', $v);
    }

    private function quoteValue(string $v): string
    {
        return sprintf("'%s'", $v);
    }

    private function getPlaceholdersName(array $placeholders): array
    {
        return array_map(function ($v) {
            return sprintf('${%s}', $v);
        }, array_keys($placeholders));
    }
}
