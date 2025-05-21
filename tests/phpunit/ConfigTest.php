<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use PHPUnit\Framework\TestCase;
use Keboola\TransformationPatternScd\Configuration\GenerateDefinition;
use Keboola\TransformationPatternScd\Config;
use PHPUnit\Framework\Assert;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigTest extends TestCase
{

    /**
     * @dataProvider validConfigProvider
     */
    public function testValidConfig(array $config): void
    {
        $appConfig = new Config($config, new GenerateDefinition());

        Assert::assertEquals($config, $appConfig->getData());
    }

    public function testDefaultValues(): void
    {
        $config = $this->getMinimalConfig();
        $expectedDiff = [
            'deleted_flag' => false,
            'use_datetime' => false,
            'keep_del_active' => false,
            'start_date_name' => 'start_date',
            'end_date_name' => 'end_date',
            'actual_name' => 'actual',
            'is_deleted_name' => 'is_deleted',
            'deleted_flag_value' => '0/1',
            'end_date_value' => '9999-12-31',
            'current_timestamp_minus_one' => false,
            'uppercase_columns' => false,
            'effective_date_adjustment' => '0',
            'snapshot_table_name' => '',
        ];

        $appConfig = new Config($config, new GenerateDefinition());

        $actualDiff = array_diff($appConfig->getData()['parameters'], $config['parameters']);

        Assert::assertEquals($expectedDiff, $actualDiff);
    }

    public function testSnapshotTableNameParameter(): void
    {
        $config = $this->getMinimalConfig();
        $config['parameters']['snapshot_table_name'] = 'custom_snapshot';

        $appConfig = new Config($config, new GenerateDefinition());

        Assert::assertEquals(
            'custom_snapshot',
            $appConfig->getData()['parameters']['snapshot_table_name']
        );
    }

    public function testInvalidScdType(): void
    {
        $config = $this->getMinimalConfig();
        $config['parameters']['scd_type'] = 'invalidScdType';

        $expectedMessage = 'The value "invalidScdType" is not allowed for path "root.parameters.scd_type". ';
        $expectedMessage .= 'Permissible values: "scd2", "scd4"';

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);
        new Config($config, new GenerateDefinition());
    }

    public function testMissingPrimaryKey(): void
    {
        $config = [
            'parameters' => [
                '_componentId' => 'keboola.snowflake-transformation',
                'scd_type' => GenerateDefinition::SCD_TYPE_2,
                'timezone' => 'Europe/Prague',
                'monitored_parameters' => 'abc,def',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "primary_key" under "root.parameters" must be configured.');
        new Config($config, new GenerateDefinition());
    }

    public function testMissingTimezone(): void
    {
        $config = [
            'parameters' => [
                '_componentId' => 'keboola.snowflake-transformation',
                'scd_type' => GenerateDefinition::SCD_TYPE_2,
                'primary_key' => 'testKey, testKey2',
                'monitored_parameters' => 'abc,def',
            ],
        ];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The child config "timezone" under "root.parameters" must be configured.');
        new Config($config, new GenerateDefinition());
    }

    public function validConfigProvider(): array
    {
        return [
            [
                [
                    'parameters' => [
                        '_componentId' => 'keboola.snowflake-transformation',
                        'scd_type' => GenerateDefinition::SCD_TYPE_2,
                        'primary_key' => 'testKey, testKey2',
                        'monitored_parameters' => 'abc,def',
                        'timezone' => 'Europe/Prague',
                        'deleted_flag' => false,
                        'use_datetime' => false,
                        'keep_del_active' => false,
                        'start_date_name' => 'start_date',
                        'end_date_name' => 'end_date',
                        'actual_name' => 'actual',
                        'is_deleted_name' => 'isDeleted',
                        'deleted_flag_value' => '0/1',
                        'end_date_value' => '9999-12-31',
                        'current_timestamp_minus_one' => false,
                        'uppercase_columns' => false,
                        'effective_date_adjustment' => '0',
                        'snapshot_table_name' => '',
                    ],
                ],
            ],
            [
                [
                    'parameters' => [
                        '_componentId' => 'keboola.snowflake-transformation',
                        'scd_type' => GenerateDefinition::SCD_TYPE_2,
                        'primary_key' => 'testKey, testKey2',
                        'monitored_parameters' => 'abc,def',
                        'timezone' => 'Europe/Prague',
                        'deleted_flag' => true,
                        'use_datetime' => false,
                        'keep_del_active' => true,
                        'start_date_name' => 'start_date',
                        'end_date_name' => 'end_date',
                        'actual_name' => 'actual',
                        'is_deleted_name' => 'isDeleted',
                        'deleted_flag_value' => '0/1',
                        'end_date_value' => '9999-12-31',
                        'current_timestamp_minus_one' => false,
                        'uppercase_columns' => true,
                        'effective_date_adjustment' => '0',
                        'snapshot_table_name' => '',
                    ],
                ],
            ],
            [
                [
                    'parameters' => [
                        '_componentId' => 'keboola.snowflake-transformation',
                        'scd_type' => GenerateDefinition::SCD_TYPE_4,
                        'primary_key' => 'testKey, testKey2',
                        'monitored_parameters' => 'abc,def',
                        'timezone' => 'Europe/Prague',
                        'deleted_flag' => true,
                        'use_datetime' => true,
                        'keep_del_active' => true,
                        'start_date_name' => 'start_date',
                        'end_date_name' => 'end_date',
                        'actual_name' => 'actual',
                        'is_deleted_name' => 'isDeleted',
                        'deleted_flag_value' => '0/1',
                        'end_date_value' => '9999-12-31',
                        'current_timestamp_minus_one' => false,
                        'uppercase_columns' => true,
                        'effective_date_adjustment' => '0',
                        'snapshot_table_name' => '',
                    ],
                ],
            ],
            [
                [
                    'parameters' => [
                        '_componentId' => 'keboola.snowflake-transformation',
                        'scd_type' => GenerateDefinition::SCD_TYPE_2,
                        'primary_key' => 'testKey, testKey2',
                        'monitored_parameters' => 'abc,def',
                        'timezone' => 'Europe/Prague',
                        'deleted_flag' => false,
                        'use_datetime' => false,
                        'keep_del_active' => false,
                        'start_date_name' => 'start_date',
                        'end_date_name' => 'end_date',
                        'actual_name' => 'actual',
                        'is_deleted_name' => 'isDeleted',
                        'deleted_flag_value' => '0/1',
                        'end_date_value' => '9999-12-31',
                        'current_timestamp_minus_one' => false,
                        'uppercase_columns' => false,
                        'effective_date_adjustment' => '0',
                        'snapshot_table_name' => 'custom_snapshot',
                    ],
                ],
            ],
        ];
    }

    private function getMinimalConfig(): array
    {
        return [
            'parameters' => [
                '_componentId' => 'keboola.snowflake-transformation',
                'scd_type' => GenerateDefinition::SCD_TYPE_2,
                'primary_key' => 'testKey, testKey2',
                'monitored_parameters' => 'abc,def',
                'timezone' => 'Europe/Prague',
            ],
        ];
    }
}
