<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\Mapping\InputMapping;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\Scd2Pattern;
use PHPUnit\Framework\TestCase;

class InputMappingTest extends TestCase
{
    public function testInputMappingWithDefaultSnapshotName(): void
    {
        // Create config mock with empty snapshot_table_name
        $config = $this->createMock(Config::class);
        $config->method('getInputTables')->willReturn([
            [
                'source' => 'in.c-test-bucket.source-table',
                'destination' => 'input_table',
            ],
        ]);
        $config->method('getSnapshotTableName')->willReturn('');
        $config->method('getData')->willReturn(['parameters' => ['test' => 'value']]);
        $config->method('getUppercaseColumns')->willReturn(false);

        // Create pattern mock
        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getInputTableName')->willReturn('input_table');
        $pattern->method('getSnapshotInputTable')->willReturn('current_snapshot');

        // Setup Parameters mock
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getActualName')->willReturn('actual');
        $parameters->method('getDeletedFlagValue')->willReturn([1, '1']);
        $pattern->method('getParameters')->willReturn($parameters);

        // Create InputMapping
        $inputMapping = new InputMapping($config, $pattern);
        $snapshotTable = $inputMapping->getSnapshotTable();

        // Source should contain hash and 'snapshot' suffix
        $source = $snapshotTable->getSource();
        $this->assertMatchesRegularExpression(
            '/out\.c-test-bucket\.source-table_[a-f0-9]{6}_snapshot/',
            $source
        );
    }

    public function testInputMappingWithCustomSnapshotName(): void
    {
        // Create config mock with custom snapshot_table_name
        $config = $this->createMock(Config::class);
        $config->method('getInputTables')->willReturn([
            [
                'source' => 'in.c-test-bucket.source-table',
                'destination' => 'input_table',
            ],
        ]);
        $config->method('getSnapshotTableName')->willReturn('-daily_snapshot');
        $config->method('getData')->willReturn(['parameters' => ['test' => 'value']]);
        $config->method('getUppercaseColumns')->willReturn(false);

        // Create pattern mock
        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getInputTableName')->willReturn('input_table');
        $pattern->method('getSnapshotInputTable')->willReturn('current_snapshot');

        // Setup Parameters mock
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getActualName')->willReturn('actual');
        $parameters->method('getDeletedFlagValue')->willReturn([1, '1']);
        $pattern->method('getParameters')->willReturn($parameters);

        // Create InputMapping
        $inputMapping = new InputMapping($config, $pattern);
        $snapshotTable = $inputMapping->getSnapshotTable();

        // Source should be direct with custom suffix
        $source = $snapshotTable->getSource();
        $this->assertEquals('out.c-test-bucket.source-table-daily_snapshot', $source);
    }

    public function testUppercaseColumnsWithDefaultActualName(): void
    {
        // Create config mock with uppercase_columns = true
        $config = $this->createMock(Config::class);
        $config->method('getInputTables')->willReturn([
            [
                'source' => 'in.c-test-bucket.source-table',
                'destination' => 'input_table',
            ],
        ]);
        $config->method('getSnapshotTableName')->willReturn('');
        $config->method('getData')->willReturn(['parameters' => ['test' => 'value']]);
        $config->method('getUppercaseColumns')->willReturn(true);

        // Create pattern mock
        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getInputTableName')->willReturn('input_table');
        $pattern->method('getSnapshotInputTable')->willReturn('current_snapshot');

        // Setup Parameters mock
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getActualName')->willReturn('actual');
        $parameters->method('getDeletedFlagValue')->willReturn([1, '1']);
        $pattern->method('getParameters')->willReturn($parameters);

        // Create InputMapping
        $inputMapping = new InputMapping($config, $pattern);
        $snapshotTableArray = $inputMapping->getSnapshotTable()->toArray();

        // Where column should be uppercase ACTUAL
        $this->assertEquals('ACTUAL', $snapshotTableArray['where_column']);
    }

    public function testUppercaseColumnsWithCustomActualName(): void
    {
        // Create config mock with uppercase_columns = true
        $config = $this->createMock(Config::class);
        $config->method('getInputTables')->willReturn([
            [
                'source' => 'in.c-test-bucket.source-table',
                'destination' => 'input_table',
            ],
        ]);
        $config->method('getSnapshotTableName')->willReturn('');
        $config->method('getData')->willReturn(['parameters' => ['test' => 'value']]);
        $config->method('getUppercaseColumns')->willReturn(true);

        // Create pattern mock
        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getInputTableName')->willReturn('input_table');
        $pattern->method('getSnapshotInputTable')->willReturn('current_snapshot');

        // Setup Parameters mock
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getActualName')->willReturn('is_actual');
        $parameters->method('getDeletedFlagValue')->willReturn([1, '1']);
        $pattern->method('getParameters')->willReturn($parameters);

        // Create InputMapping
        $inputMapping = new InputMapping($config, $pattern);
        $snapshotTableArray = $inputMapping->getSnapshotTable()->toArray();

        // Where column should be uppercase IS_ACTUAL
        $this->assertEquals('IS_ACTUAL', $snapshotTableArray['where_column']);
    }

    public function testCustomDeletedFlagValue(): void
    {
        // Create config mock
        $config = $this->createMock(Config::class);
        $config->method('getInputTables')->willReturn([
            [
                'source' => 'in.c-test-bucket.source-table',
                'destination' => 'input_table',
            ],
        ]);
        $config->method('getSnapshotTableName')->willReturn('');
        $config->method('getData')->willReturn(['parameters' => ['test' => 'value']]);
        $config->method('getUppercaseColumns')->willReturn(false);

        // Create pattern mock
        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getInputTableName')->willReturn('input_table');
        $pattern->method('getSnapshotInputTable')->willReturn('current_snapshot');

        // Setup Parameters mock with custom deleted flag value
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getActualName')->willReturn('actual');
        $parameters->method('getDeletedFlagValue')->willReturn(["'no'", "'yes'"]);
        $pattern->method('getParameters')->willReturn($parameters);

        // Create InputMapping
        $inputMapping = new InputMapping($config, $pattern);
        $snapshotTableArray = $inputMapping->getSnapshotTable()->toArray();

        // Where values should be ["yes"] (without quotes)
        $this->assertEquals(['yes'], $snapshotTableArray['where_values']);
    }
}
