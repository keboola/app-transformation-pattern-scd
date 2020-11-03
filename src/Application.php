<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd;

use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Psr\Log\LoggerInterface;
use SqlFormatter;

class Application
{
    private const COL_SNAP_PK = 'snap_pk';
    private const COL_START_DATE = 'start_date';
    private const COL_END_DATE = 'end_date';
    private const COL_ACTUAL = 'actual';
    private const COL_IS_DELETED = 'is_deleted';
    private const COL_SNAP_DATE = 'snapshot_date';

    private Config $config;

    private LoggerInterface $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function generate(): array
    {
        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                $sqlTemplate = $this->generateScd2Type();
                break;
            case GenerateDefinition::SCD_TYPE_4:
                $sqlTemplate = $this->generateScd4Type();
                break;
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }

        $sqls = SqlFormatter::splitQuery($sqlTemplate);

        return array_map(function ($sql) {
            return SqlFormatter::format($sql, false);
        }, $sqls);
    }

    public function createDestinationTable(Client $client, string $datadir, string $tableId): string
    {
        $header = $this->buildDestinationTableHeader(
            $this->config->getPrimaryKey(),
            $this->config->getMonitoredParameters()
        );

        $csvFile = new CsvFile(sprintf('%s.dst.csv', $datadir));
        $csvFile->writeRow($header);

        $tableInfo = explode('.', $tableId);

        $destinationTableName = sprintf('curr_snapshot_%s', $tableInfo[2]);

        $client->createTable(
            sprintf('%s.%s', $tableInfo[0], $tableInfo[1]),
            $destinationTableName,
            $csvFile,
            ['primaryKey'=> implode(',', $this->config->getPrimaryKey())]
        );

        return $destinationTableName;
    }

    private function buildDestinationTableHeader(array $primaryKey, array $monitoredColumns): array
    {
        $header = [];
        $header[] = self::COL_SNAP_PK;
        $header = array_merge($header, $primaryKey, $monitoredColumns);

        switch ($this->config->getScdType()) {
            case GenerateDefinition::SCD_TYPE_2:
                $header[] = self::COL_START_DATE;
                $header[] = self::COL_END_DATE;
                break;
            case GenerateDefinition::SCD_TYPE_4:
                $header[] = self::COL_SNAP_DATE;
                break;
            default:
                throw new UserException(
                    sprintf('Unknown scd type "%s"', $this->config->getScdType())
                );
        }

        $header[] = self::COL_ACTUAL;

        if ($this->config->hasDeletedFlag()) {
            $header[] = self::COL_IS_DELETED;
        }

        array_walk($header, function ($v) {
            return mb_strtolower($v);
        });

        return $header;
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
            $placeholders['actual_deleted_timestamp'] = '9999-12-31 00:00:00';
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

    private function getPlaceholdersName(array $placeholders): array
    {
        return array_map(function ($v) {
            return sprintf('${%s}', $v);
        }, array_keys($placeholders));
    }
}
