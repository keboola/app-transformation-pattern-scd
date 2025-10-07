<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Parameters;

use Generator;
use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\Application;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\InputTableResolver;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Parameters\ParametersFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParametersFactoryTest extends TestCase
{
    /** @var Config&MockObject */
    private $config;
    /** @var InputTableResolver&MockObject */
    private $inputTableResolver;
    private ParametersFactory $factory;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->inputTableResolver = $this->createMock(InputTableResolver::class);
        $this->factory = new ParametersFactory($this->config, $this->inputTableResolver);
    }

    public function testCreateWithSnowflakeComponent(): void
    {
        $this->setupSnowflakeConfig();
        $this->setupInputTableResolver();

        $parameters = $this->factory->create();

        $this->assertInstanceOf(Parameters::class, $parameters);
        $this->assertEquals(Parameters::BACKEND_SNOWFLAKE, $parameters->getBackend());
        $this->assertEquals(['id', 'name'], $parameters->getPrimaryKey());
        $this->assertEquals(['email', 'phone'], $parameters->getMonitoredParameters());
        $this->assertEquals('Europe/Prague', $parameters->getTimezone());
        $this->assertEquals(true, $parameters->hasDeletedFlag());
        $this->assertEquals(false, $parameters->useDatetime());
        $this->assertEquals(true, $parameters->keepDeleteActive());
        $this->assertEquals('start_date', $parameters->getStartDateName());
        $this->assertEquals('end_date', $parameters->getEndDateName());
        $this->assertEquals('actual', $parameters->getActualName());
        $this->assertEquals('is_deleted', $parameters->getIsDeletedName());
        $this->assertEquals(['0', '1'], $parameters->getDeletedFlagValue());
        $this->assertEquals('9999-12-31', $parameters->getEndDateValue());
        $this->assertEquals(false, $parameters->getCurrentTimestampMinusOne());
        $this->assertEquals(true, $parameters->getUppercaseColumns());
        $this->assertEquals(0, $parameters->getEffectiveDateAdjustment());
        $this->assertEquals('my_snapshot', $parameters->getSnapshotTableName());
        $this->assertEquals([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
            ],
        ], $parameters->getInputTableDefinition());
    }

    public function testCreateWithUnsupportedComponent(): void
    {
        $this->config->method('getComponentId')->willReturn('unsupported-component');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The SCD code pattern is not compatible with component "unsupported-component".');

        $this->factory->create();
    }

    public function testCreateWithMissingPrimaryKeyColumns(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->method('getInputTableColumns')->willReturn(['id', 'email']);
        $this->inputTableResolver->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Primary key "name" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    public function testCreateWithMissingMonitoredParameters(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->method('getInputTableColumns')->willReturn(['id', 'name', 'email']);
        $this->inputTableResolver->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Monitored parameter "phone" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    public function testCreateWithMultipleMissingColumns(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->method('getInputTableColumns')->willReturn(['id']);
        $this->inputTableResolver->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Primary key "name" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    /**
     * @dataProvider componentProvider
     */
    public function testGetBackendWithDifferentComponents(string $componentId, string $expectedBackend): void
    {
        $this->config->method('getComponentId')->willReturn($componentId);
        $this->setupInputTableResolver();

        if ($expectedBackend === 'exception') {
            $this->expectException(UserException::class);
            $this->factory->create();
        } else {
            $parameters = $this->factory->create();
            $this->assertEquals($expectedBackend, $parameters->getBackend());
        }
    }

    public function componentProvider(): Generator
    {
        yield 'snowflake component' => [Application::SNOWFLAKE_TRANS_COMPONENT, Parameters::BACKEND_SNOWFLAKE];
        yield 'unsupported component' => ['unsupported-component', 'exception'];
        yield 'empty component' => ['', 'exception'];
    }

    private function setupSnowflakeConfig(): void
    {
        $this->config->method('getComponentId')->willReturn(Application::SNOWFLAKE_TRANS_COMPONENT);
        $this->config->method('getTimezone')->willReturn('Europe/Prague');
        $this->config->method('hasDeletedFlag')->willReturn(true);
        $this->config->method('useDatetime')->willReturn(false);
        $this->config->method('keepDeleteActive')->willReturn(true);
        $this->config->method('getStartDateName')->willReturn('start_date');
        $this->config->method('getEndDateName')->willReturn('end_date');
        $this->config->method('getActualName')->willReturn('actual');
        $this->config->method('getIsDeletedName')->willReturn('is_deleted');
        $this->config->method('getDeletedFlagValue')->willReturn('0/1');
        $this->config->method('getEndDateValue')->willReturn('9999-12-31');
        $this->config->method('getCurrentTimestampMinusOne')->willReturn(false);
        $this->config->method('getUppercaseColumns')->willReturn(true);
        $this->config->method('getEffectiveDateAdjustment')->willReturn(0);
        $this->config->method('getSnapshotTableName')->willReturn('my_snapshot');
        $this->config->method('getPrimaryKeyInput')->willReturn(['id', 'name']);
        $this->config->method('getIncludedParametersInput')->willReturn(['email', 'phone']);
    }

    private function setupInputTableResolver(): void
    {
        $this->inputTableResolver->method('getInputTableColumns')->willReturn(['id', 'name', 'email', 'phone']);
        $this->inputTableResolver->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);
    }
}
