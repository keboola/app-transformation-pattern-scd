<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Parameters;

use Generator;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{

    public function testBackendConstant(): void
    {
        self::assertEquals('snowflake', Parameters::BACKEND_SNOWFLAKE);
    }

    /**
     * @dataProvider deletedFlagValueProvider
     */
    public function testGetDeletedFlagValueWithDataProvider(string $input, array $expected): void
    {
        $parameters = new Parameters(
            Parameters::BACKEND_SNOWFLAKE,
            ['id'],
            [],
            'UTC',
            true,
            false,
            false,
            'start_date',
            'end_date',
            'actual',
            'is_deleted',
            $input,
            '9999-12-31',
            false,
            false,
            0
        );

        self::assertEquals($expected, $parameters->getDeletedFlagValue());
    }

    public function deletedFlagValueProvider(): Generator
    {
        yield 'numeric values' => ['0/1', ['0', '1']];
        yield 'string values' => ['no/yes', ['no', 'yes']];
        yield 'mixed values' => ['false/true', ['false', 'true']];
        yield 'single character' => ['a/b', ['a', 'b']];
        yield 'empty strings' => ['/', ['', '']];
    }

    /**
     * @dataProvider configProvider
     */
    public function testParametersWithConfig(array $config): void
    {
        $parameters = new Parameters(
            $config['backend'],
            $config['primaryKey'],
            $config['monitoredParameters'],
            $config['timezone'],
            $config['deletedFlag'],
            $config['useDatetime'],
            $config['keepDeletedActive'],
            $config['startDateName'],
            $config['endDateName'],
            $config['actualName'],
            $config['isDeletedName'],
            $config['deletedFlagValue'],
            $config['endDateValue'],
            $config['currentTimestampMinusOne'],
            $config['uppercaseColumns'],
            $config['effectiveDateAdjustment'],
            $config['snapshotTableName'],
            $config['inputTableDefinition'],
        );

        self::assertEquals($config['backend'], $parameters->getBackend());
        self::assertEquals($config['primaryKey'], $parameters->getPrimaryKey());
        self::assertEquals($config['monitoredParameters'], $parameters->getMonitoredParameters());
        self::assertEquals($config['timezone'], $parameters->getTimezone());
        self::assertEquals($config['deletedFlag'], $parameters->hasDeletedFlag());
        self::assertEquals($config['useDatetime'], $parameters->useDatetime());
        self::assertEquals($config['keepDeletedActive'], $parameters->keepDeleteActive());
        self::assertEquals($config['startDateName'], $parameters->getStartDateName());
        self::assertEquals($config['endDateName'], $parameters->getEndDateName());
        self::assertEquals($config['actualName'], $parameters->getActualName());
        self::assertEquals($config['isDeletedName'], $parameters->getIsDeletedName());
        self::assertEquals(explode('/', $config['deletedFlagValue']), $parameters->getDeletedFlagValue());
        self::assertEquals($config['endDateValue'], $parameters->getEndDateValue());
        self::assertEquals($config['currentTimestampMinusOne'], $parameters->getCurrentTimestampMinusOne());
        self::assertEquals($config['uppercaseColumns'], $parameters->getUppercaseColumns());
        self::assertEquals($config['effectiveDateAdjustment'], $parameters->getEffectiveDateAdjustment());
        self::assertEquals($config['inputTableDefinition'], $parameters->getInputTableDefinition());
    }

    public function configProvider(): Generator
    {
        yield 'snowflake config with datetime' => [[
            'backend' => Parameters::BACKEND_SNOWFLAKE,
            'primaryKey' => ['id', 'name'],
            'monitoredParameters' => ['email', 'phone'],
            'timezone' => 'Europe/Prague',
            'deletedFlag' => true,
            'useDatetime' => true,
            'keepDeletedActive' => false,
            'startDateName' => 'start_date',
            'endDateName' => 'end_date',
            'actualName' => 'actual',
            'isDeletedName' => 'is_deleted',
            'deletedFlagValue' => '0/1',
            'endDateValue' => '9999-12-31',
            'currentTimestampMinusOne' => false,
            'uppercaseColumns' => true,
            'effectiveDateAdjustment' => 0,
            'snapshotTableName' => 'tableName',
            'inputTableDefinition' => [],
        ]];

        yield 'minimal config' => [[
            'backend' => Parameters::BACKEND_SNOWFLAKE,
            'primaryKey' => ['id'],
            'monitoredParameters' => [],
            'timezone' => 'UTC',
            'deletedFlag' => false,
            'useDatetime' => false,
            'keepDeletedActive' => false,
            'startDateName' => 'start_date',
            'endDateName' => 'end_date',
            'actualName' => 'actual',
            'isDeletedName' => 'is_deleted',
            'deletedFlagValue' => 'no/yes',
            'endDateValue' => '9999-12-31',
            'currentTimestampMinusOne' => true,
            'uppercaseColumns' => false,
            'effectiveDateAdjustment' => 1,
            'snapshotTableName' => 'tableName',
            'inputTableDefinition' => [],
        ]];

        yield 'config with keep deleted active' => [[
            'backend' => Parameters::BACKEND_SNOWFLAKE,
            'primaryKey' => ['user_id'],
            'monitoredParameters' => ['status', 'role'],
            'timezone' => 'America/New_York',
            'deletedFlag' => true,
            'useDatetime' => false,
            'keepDeletedActive' => true,
            'startDateName' => 'valid_from',
            'endDateName' => 'valid_to',
            'actualName' => 'is_current',
            'isDeletedName' => 'deleted',
            'deletedFlagValue' => 'false/true',
            'endDateValue' => '2099-12-31',
            'currentTimestampMinusOne' => true,
            'uppercaseColumns' => false,
            'effectiveDateAdjustment' => 2,
            'snapshotTableName' => 'tableName',
            'inputTableDefinition' => [
                'columns' => [
                    ['name' => 'user_id', 'definition' => ['type' => 'VARCHAR']],
                    ['name' => 'status', 'definition' => ['type' => 'VARCHAR']],
                    ['name' => 'role', 'definition' => ['type' => 'VARCHAR']],
                    ['name' => 'created_at', 'definition' => ['type' => 'TIMESTAMP']],
                ],
            ],
        ]];
    }
}
