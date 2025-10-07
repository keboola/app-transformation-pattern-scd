<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Patterns;

use Generator;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\Scd2Pattern;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Scd2PatternTest extends TestCase
{
    private Scd2Pattern $pattern;

    protected function setUp(): void
    {
        $this->pattern = new Scd2Pattern();
    }

    public function testGetterMethods(): void
    {
        $this->assertEquals('input_table', $this->pattern->getInputTableName());
        $this->assertEquals('current_snapshot', $this->pattern->getSnapshotInputTable());
        $this->assertEquals('new_snapshot', $this->pattern->getSnapshotOutputTable());
    }

    /**
     * @dataProvider uppercaseColumnsProvider
     */
    public function testGetSnapshotPrimaryKey(bool $uppercaseColumns, string $expectedResult): void
    {
        $pattern = new Scd2Pattern();
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->method('getUppercaseColumns')->willReturn($uppercaseColumns);
        $pattern->setParameters($parameters);

        $result = $pattern->getSnapshotPrimaryKey();
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetTemplateVariables(): void
    {
        $pattern = new Scd2Pattern();
        $parameters = $this->createMock(Parameters::class);

        // Setup parameters for this test
        $parameters->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->method('useDatetime')->willReturn(true);
        $parameters->method('keepDeleteActive')->willReturn(false);
        $parameters->method('hasDeletedFlag')->willReturn(true);
        $parameters->method('getPrimaryKey')->willReturn(['id', 'name']);
        $parameters->method('getUppercaseColumns')->willReturn(false);
        $parameters->method('getStartDateName')->willReturn('start_date');
        $parameters->method('getEndDateName')->willReturn('end_date');
        $parameters->method('getActualName')->willReturn('actual');
        $parameters->method('getIsDeletedName')->willReturn('is_deleted');
        $parameters->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->method('getEndDateValue')->willReturn('9999-12-31');
        $parameters->method('getCurrentTimestampMinusOne')->willReturn(false);
        $parameters->method('getEffectiveDateAdjustment')->willReturn(0);
        $parameters->method('getMonitoredParameters')->willReturn(['email', 'phone']);
        $parameters->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'email', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'phone', 'definition' => ['type' => 'VARCHAR']],
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
        $this->assertEquals(true, $result['useDatetime']);
        $this->assertEquals(false, $result['keepDeleteActive']);
        $this->assertEquals(true, $result['hasDeletedFlag']);
        $this->assertEquals(['id', 'name'], $result['inputPrimaryKey']);
        $this->assertEquals(['id', 'name'], $result['inputPrimaryKeyLower']);
        $this->assertEquals(['id', 'name', 'email', 'phone'], $result['inputColumns']);
        $this->assertEquals('snapshot_pk', $result['snapshotPrimaryKeyName']);
        $this->assertEquals(['id', 'name', 'start_date'], $result['snapshotPrimaryKeyParts']);
        $this->assertEquals(['id', 'name', 'email', 'phone'], $result['snapshotInputColumns']);
        $this->assertEquals(
            ['id', 'name', 'email', 'phone', 'start_date', 'end_date', 'actual', 'is_deleted'],
            $result['snapshotAllColumnsExceptPk']
        );
        $this->assertEquals('0', $result['deletedActualValue']);
        $this->assertEquals([
            'input' => 'input_table',
            'currentSnapshot' => 'current_snapshot',
            'newSnapshot' => 'new_snapshot',
        ], $result['tableName']);
        $this->assertEquals(['start_date', 'end_date', 'actual', 'is_deleted'], array_values($result['columnName']));
        $this->assertEquals('9999-12-31', $result['endDateValue']);
        $this->assertEquals(['0', '1'], $result['deletedFlagValue']);
        $this->assertEquals(false, $result['currentTimestampMinusOne']);
        $this->assertEquals(false, $result['uppercaseColumns']);
        $this->assertEquals(0, $result['effectiveDateAdjustment']);
    }

    public function testGetTemplateVariablesWithUppercaseColumns(): void
    {
        $pattern = new Scd2Pattern();
        $parameters = $this->createMock(Parameters::class);

        // Setup parameters for this test
        $parameters->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->method('useDatetime')->willReturn(false);
        $parameters->method('keepDeleteActive')->willReturn(true);
        $parameters->method('hasDeletedFlag')->willReturn(false);
        $parameters->method('getPrimaryKey')->willReturn(['ID', 'NAME']);
        $parameters->method('getUppercaseColumns')->willReturn(true);
        $parameters->method('getStartDateName')->willReturn('START_DATE');
        $parameters->method('getEndDateName')->willReturn('END_DATE');
        $parameters->method('getActualName')->willReturn('ACTUAL');
        $parameters->method('getIsDeletedName')->willReturn('IS_DELETED');
        $parameters->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->method('getEndDateValue')->willReturn('9999-12-31');
        $parameters->method('getCurrentTimestampMinusOne')->willReturn(true);
        $parameters->method('getEffectiveDateAdjustment')->willReturn(1);
        $parameters->method('getMonitoredParameters')->willReturn(['EMAIL', 'PHONE']);
        $parameters->method('getInputTableDefinition')->willReturn([
            'columns' => [
                ['name' => 'ID', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'NAME', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'EMAIL', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'PHONE', 'definition' => ['type' => 'VARCHAR']],
            ],
        ]);

        $pattern->setParameters($parameters);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($pattern);
        $method = $reflection->getMethod('getTemplateVariables');
        $method->setAccessible(true);
        $result = $method->invoke($pattern);

        $this->assertIsArray($result);
        $this->assertEquals('SNAPSHOT_PK', $result['snapshotPrimaryKeyName']);
        $this->assertEquals(['id', 'name', 'start_date'], $result['snapshotPrimaryKeyParts']);
        $this->assertEquals(['ID', 'NAME', 'EMAIL', 'PHONE'], $result['snapshotInputColumns']);
        $this->assertEquals(
            ['ID', 'NAME', 'EMAIL', 'PHONE', 'START_DATE', 'END_DATE', 'ACTUAL'],
            $result['snapshotAllColumnsExceptPk']
        );
        $this->assertEquals('1', $result['deletedActualValue']); // keepDeleteActive = true
        $this->assertEquals(
            ['START_DATE', 'END_DATE', 'ACTUAL'],
            array_values($result['columnName'])
        ); // no deleted flag
        $this->assertEquals(true, $result['uppercaseColumns']);
    }

    public function uppercaseColumnsProvider(): Generator
    {
        yield 'with uppercase columns' => [true, 'SNAPSHOT_PK'];
        yield 'without uppercase columns' => [false, 'snapshot_pk'];
    }
}
