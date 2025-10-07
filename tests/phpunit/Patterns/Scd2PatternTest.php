<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Patterns;

use Generator;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\Scd2Pattern;
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
        $parameters->expects($this->once())->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->expects($this->once())->method('getUppercaseColumns')->willReturn($uppercaseColumns);
        $pattern->setParameters($parameters);

        $result = $pattern->getSnapshotPrimaryKey();
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetTemplateVariables(): void
    {
        $pattern = new Scd2Pattern();
        $parameters = $this->createMock(Parameters::class);

        // Setup parameters for this test
        $parameters->expects($this->once())->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->expects($this->once())->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->expects($this->exactly(5))->method('useDatetime')->willReturn(true);
        $parameters->expects($this->exactly(2))->method('keepDeleteActive')->willReturn(false);
        $parameters->expects($this->exactly(3))->method('hasDeletedFlag')->willReturn(true);
        $parameters->expects($this->exactly(6))->method('getPrimaryKey')->willReturn(['id', 'name']);
        $parameters->expects($this->exactly(6))->method('getUppercaseColumns')->willReturn(false);
        $parameters->expects($this->exactly(3))->method('getStartDateName')->willReturn('start_date');
        $parameters->expects($this->exactly(2))->method('getEndDateName')->willReturn('end_date');
        $parameters->expects($this->exactly(2))->method('getActualName')->willReturn('actual');
        $parameters->expects($this->exactly(2))->method('getIsDeletedName')->willReturn('is_deleted');
        $parameters->expects($this->exactly(10))->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->expects($this->once())->method('getEndDateValue')->willReturn('9999-12-31');
        $parameters->expects($this->once())->method('getCurrentTimestampMinusOne')->willReturn(false);
        $parameters->expects($this->once())->method('getEffectiveDateAdjustment')->willReturn(0);
        $parameters->expects($this->exactly(3))->method('getMonitoredParameters')->willReturn(['email', 'phone']);
        $parameters->expects($this->exactly(5))->method('getInputTableDefinition')->willReturn([
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
        $parameters->expects($this->once())->method('getBackend')->willReturn(Parameters::BACKEND_SNOWFLAKE);
        $parameters->expects($this->once())->method('getTimezone')->willReturn('Europe/Prague');
        $parameters->expects($this->exactly(5))->method('useDatetime')->willReturn(false);
        $parameters->expects($this->exactly(2))->method('keepDeleteActive')->willReturn(true);
        $parameters->expects($this->exactly(3))->method('hasDeletedFlag')->willReturn(false);
        $parameters->expects($this->exactly(6))->method('getPrimaryKey')->willReturn(['ID', 'NAME']);
        $parameters->expects($this->exactly(6))->method('getUppercaseColumns')->willReturn(true);
        $parameters->expects($this->exactly(3))->method('getStartDateName')->willReturn('START_DATE');
        $parameters->expects($this->exactly(2))->method('getEndDateName')->willReturn('END_DATE');
        $parameters->expects($this->exactly(2))->method('getActualName')->willReturn('ACTUAL');
        $parameters->expects($this->never())->method('getIsDeletedName')->willReturn('IS_DELETED');
        $parameters->expects($this->exactly(2))->method('getDeletedFlagValue')->willReturn(['0', '1']);
        $parameters->expects($this->once())->method('getEndDateValue')->willReturn('9999-12-31');
        $parameters->expects($this->once())->method('getCurrentTimestampMinusOne')->willReturn(true);
        $parameters->expects($this->once())->method('getEffectiveDateAdjustment')->willReturn(1);
        $parameters->expects($this->exactly(3))->method('getMonitoredParameters')->willReturn(['EMAIL', 'PHONE']);
        $parameters->expects($this->exactly(5))->method('getInputTableDefinition')->willReturn([
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
