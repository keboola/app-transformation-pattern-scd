<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Patterns;

use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\Scd4Pattern;
use PHPUnit\Framework\TestCase;

class Scd4PatternTest extends TestCase
{
    private Scd4Pattern $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Scd4Pattern();
    }

    public function testGetterMethods(): void
    {
        self::assertEquals('input_table', $this->pattern->getInputTableName());
        self::assertEquals('current_snapshot', $this->pattern->getSnapshotInputTable());
        self::assertEquals('new_snapshot', $this->pattern->getSnapshotOutputTable());
        self::assertEquals('snapshot_pk', $this->pattern->getSnapshotPrimaryKey());
    }

    public function testGetTemplateVariables(): void
    {
        $pattern = new Scd4Pattern();
        $parameters = $this->createMock(Parameters::class);
        $parameters->expects($this->once())->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->expects($this->once())->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->expects($this->exactly(3))->method('useDatetime')->willReturn(false);
        $parameters->expects($this->exactly(2))->method('keepDeleteActive')->willReturn(false);
        $parameters->expects($this->exactly(4))->method('hasDeletedFlag')->willReturn(true);
        $parameters->expects($this->exactly(18))->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->expects($this->exactly(3))->method('getIsDeletedName')->willReturn('is_deleted');
        $parameters->expects($this->exactly(3))->method('getActualName')->willReturn('actual');
        $parameters->expects($this->exactly(5))->method('getPrimaryKey')->willReturn(['id', 'name']);
        $parameters->expects($this->exactly(4))->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'email', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);
        $pattern->setParameters($parameters);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($pattern);
        $method = $reflection->getMethod('getTemplateVariables');
        $method->setAccessible(true);
        $result = $method->invoke($pattern);

        self::assertIsArray($result);
        self::assertEquals('Europe/Prague', $result['timezone']);
        self::assertEquals(false, $result['useDatetime']);
        self::assertEquals(false, $result['keepDeleteActive']);
        self::assertEquals(true, $result['hasDeletedFlag']);
        self::assertEquals(['id', 'name'], $result['inputPrimaryKey']);
        self::assertEquals('snapshot_pk', $result['snapshotPrimaryKeyName']);
        self::assertEquals(0, $result['deletedActualValue']);
        self::assertEquals(true, $result['generateDeletedRecords']);
        self::assertEquals([
            'snapshotDate' => 'snapshot_date',
            'actual' => 'actual',
            'isDeleted' => 'is_deleted',
        ], $result['columnName']);
    }

    public function testGetTemplateVariablesWithKeepDeleteActive(): void
    {
        $pattern = new Scd4Pattern();
        $parameters = $this->createMock(Parameters::class);
        $parameters->expects($this->once())->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->expects($this->once())->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->expects($this->exactly(3))->method('useDatetime')->willReturn(false);
        $parameters->expects($this->exactly(3))->method('keepDeleteActive')->willReturn(true);
        $parameters->expects($this->exactly(4))->method('hasDeletedFlag')->willReturn(false);
        $parameters->expects($this->exactly(5))->method('getPrimaryKey')->willReturn(['id']);
        $parameters->expects($this->exactly(4))->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);
        $parameters->expects($this->exactly(10))->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->expects($this->exactly(3))->method('getActualName')->willReturn('actual');
        $pattern->setParameters($parameters);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($pattern);
        $method = $reflection->getMethod('getTemplateVariables');
        $method->setAccessible(true);
        $result = $method->invoke($pattern);

        self::assertEquals(true, $result['keepDeleteActive']);
        self::assertEquals(false, $result['hasDeletedFlag']);
        self::assertEquals(1, $result['deletedActualValue']);
        self::assertEquals(true, $result['generateDeletedRecords']);
    }
}
