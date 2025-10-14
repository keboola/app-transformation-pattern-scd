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

        self::assertInstanceOf(Parameters::class, $parameters);
        self::assertEquals(Parameters::BACKEND_SNOWFLAKE, $parameters->getBackend());
        self::assertEquals(['id', 'name'], $parameters->getPrimaryKey());
        self::assertEquals(['email', 'phone'], $parameters->getMonitoredParameters());
        self::assertEquals('Europe/Prague', $parameters->getTimezone());
        self::assertEquals(true, $parameters->hasDeletedFlag());
        self::assertEquals(false, $parameters->useDatetime());
        self::assertEquals(true, $parameters->keepDeleteActive());
        self::assertEquals('start_date', $parameters->getStartDateName());
        self::assertEquals('end_date', $parameters->getEndDateName());
        self::assertEquals('actual', $parameters->getActualName());
        self::assertEquals('is_deleted', $parameters->getIsDeletedName());
        self::assertEquals(['0', '1'], $parameters->getDeletedFlagValue());
        self::assertEquals('9999-12-31', $parameters->getEndDateValue());
        self::assertEquals(false, $parameters->getCurrentTimestampMinusOne());
        self::assertEquals(true, $parameters->getUppercaseColumns());
        self::assertEquals(0, $parameters->getEffectiveDateAdjustment());
        self::assertEquals('my_snapshot', $parameters->getSnapshotTableName());
        self::assertEquals([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
            ],
        ], $parameters->getInputTableDefinition());
    }

    public function testCreateWithUnsupportedComponent(): void
    {
        $this->config->expects($this->exactly(2))->method('getComponentId')->willReturn('unsupported-component');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('The SCD code pattern is not compatible with component "unsupported-component".');

        $this->factory->create();
    }

    public function testCreateWithMissingPrimaryKeyColumns(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->expects($this->once())->method('getInputTableColumns')->willReturn(['id', 'email']);
        $this->inputTableResolver->expects($this->once())->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Primary key "name" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    public function testCreateWithMissingMonitoredParameters(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->expects($this->exactly(2))
            ->method('getInputTableColumns')
            ->willReturn(['id', 'name', 'email']);
        $this->inputTableResolver->expects($this->once())->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Monitored parameter "phone" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    public function testCreateWithMultipleMissingColumns(): void
    {
        $this->setupSnowflakeConfig();
        $this->inputTableResolver->expects($this->once())->method('getInputTableColumns')->willReturn(['id']);
        $this->inputTableResolver->expects($this->once())->method('getInputTableId')->willReturn('in.c-main.test');

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Primary key "name" not found in the input table "in.c-main.test".');

        $this->factory->create();
    }

    /**
     * @dataProvider componentProvider
     */
    public function testGetBackendWithDifferentComponents(string $componentId, string $expectedBackend): void
    {
        $this->config->expects($this->any())->method('getComponentId')->willReturn($componentId);
        $this->setupInputTableResolver();

        if ($expectedBackend === 'exception') {
            $this->expectException(UserException::class);
            $this->factory->create();
        } else {
            $parameters = $this->factory->create();
            self::assertEquals($expectedBackend, $parameters->getBackend());
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
        $this->config->expects($this->once())
            ->method('getComponentId')
            ->willReturn(Application::SNOWFLAKE_TRANS_COMPONENT);
        $this->config->expects($this->any())->method('getTimezone')->willReturn('Europe/Prague');
        $this->config->expects($this->any())->method('hasDeletedFlag')->willReturn(true);
        $this->config->expects($this->any())->method('useDatetime')->willReturn(false);
        $this->config->expects($this->any())->method('keepDeleteActive')->willReturn(true);
        $this->config->expects($this->any())->method('getStartDateName')->willReturn('start_date');
        $this->config->expects($this->any())->method('getEndDateName')->willReturn('end_date');
        $this->config->expects($this->any())->method('getActualName')->willReturn('actual');
        $this->config->expects($this->any())->method('getIsDeletedName')->willReturn('is_deleted');
        $this->config->expects($this->any())->method('getDeletedFlagValue')->willReturn('0/1');
        $this->config->expects($this->any())->method('getEndDateValue')->willReturn('9999-12-31');
        $this->config->expects($this->any())->method('getCurrentTimestampMinusOne')->willReturn(false);
        $this->config->expects($this->any())->method('getUppercaseColumns')->willReturn(true);
        $this->config->expects($this->any())->method('getEffectiveDateAdjustment')->willReturn(0);
        $this->config->expects($this->any())->method('getSnapshotTableName')->willReturn('my_snapshot');
        $this->config->expects($this->once())->method('getPrimaryKeyInput')->willReturn(['id', 'name']);
        $this->config->expects($this->any())->method('getIncludedParametersInput')->willReturn(['email', 'phone']);
    }

    private function setupInputTableResolver(): void
    {
        $this->inputTableResolver->expects($this->any())
            ->method('getInputTableColumns')
            ->willReturn(['id', 'name', 'email', 'phone']);
        $this->inputTableResolver->expects($this->any())->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);
    }
}
