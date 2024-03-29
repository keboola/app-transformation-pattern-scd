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
        ];

        $appConfig = new Config($config, new GenerateDefinition());

        $actualDiff = array_diff($appConfig->getData()['parameters'], $config['parameters']);

        Assert::assertEquals($expectedDiff, $actualDiff);
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
