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
        $this->assertEquals('input_table', $this->pattern->getInputTableName());
        $this->assertEquals('current_snapshot', $this->pattern->getSnapshotInputTable());
        $this->assertEquals('new_snapshot', $this->pattern->getSnapshotOutputTable());
        $this->assertEquals('snapshot_pk', $this->pattern->getSnapshotPrimaryKey());
    }

    public function testGetTemplateVariables(): void
    {
        $pattern = new Scd4Pattern();
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->method('useDatetime')->willReturn(false);
        $parameters->method('keepDeleteActive')->willReturn(false);
        $parameters->method('hasDeletedFlag')->willReturn(true);
        $parameters->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->method('getIsDeletedName')->willReturn('is_deleted');
        $parameters->method('getPrimaryKey')->willReturn(['id', 'name']);
        $parameters->method('getInputTableDefinition')->willReturn([
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

        $this->assertIsArray($result);
        $this->assertEquals('Europe/Prague', $result['timezone']);
        $this->assertEquals(false, $result['useDatetime']);
        $this->assertEquals(false, $result['keepDeleteActive']);
        $this->assertEquals(true, $result['hasDeletedFlag']);
        $this->assertEquals(['id', 'name'], $result['inputPrimaryKey']);
        $this->assertEquals('snapshot_pk', $result['snapshotPrimaryKeyName']);
        $this->assertEquals(0, $result['deletedActualValue']);
        $this->assertEquals(true, $result['generateDeletedRecords']);
        $this->assertEquals([
            'snapshotDate' => 'snapshot_date',
            'actual' => 'actual',
            'isDeleted' => 'is_deleted',
        ], $result['columnName']);
    }

    public function testGetTemplateVariablesWithKeepDeleteActive(): void
    {
        $pattern = new Scd4Pattern();
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->method('useDatetime')->willReturn(false);
        $parameters->method('keepDeleteActive')->willReturn(true);
        $parameters->method('hasDeletedFlag')->willReturn(false);
        $parameters->method('getPrimaryKey')->willReturn(['id']);
        $parameters->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);
        $pattern->setParameters($parameters);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($pattern);
        $method = $reflection->getMethod('getTemplateVariables');
        $method->setAccessible(true);
        $result = $method->invoke($pattern);

        $this->assertEquals(true, $result['keepDeleteActive']);
        $this->assertEquals(false, $result['hasDeletedFlag']);
        $this->assertEquals(1, $result['deletedActualValue']);
        $this->assertEquals(true, $result['generateDeletedRecords']);
    }
}
