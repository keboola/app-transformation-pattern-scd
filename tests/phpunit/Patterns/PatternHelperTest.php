<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests\Patterns;

use PHPUnit\Framework\TestCase;
use Keboola\TransformationPatternScd\Patterns\PatternHelper;

class PatternHelperTest extends TestCase
{
    public function testColumnsToLower(): void
    {
        $columns = [
            ['name' => 'COLUMN1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'Column2', 'definition' => ['type' => 'INTEGER']],
            ['name' => 'column3', 'definition' => ['type' => 'DATE']],
        ];

        $result = PatternHelper::columnsToLower($columns);

        $this->assertEquals([
            ['name' => 'column1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'column2', 'definition' => ['type' => 'INTEGER']],
            ['name' => 'column3', 'definition' => ['type' => 'DATE']],
        ], $result);
    }

    public function testColumnsToUpper(): void
    {
        $columns = [
            ['name' => 'column1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'Column2', 'definition' => ['type' => 'INTEGER']],
            ['name' => 'COLUMN3', 'definition' => ['type' => 'DATE']],
        ];

        $result = PatternHelper::columnsToUpper($columns);

        $this->assertEquals([
            ['name' => 'COLUMN1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'COLUMN2', 'definition' => ['type' => 'INTEGER']],
            ['name' => 'COLUMN3', 'definition' => ['type' => 'DATE']],
        ], $result);
    }

    public function testTransformColumnsCaseWithUppercase(): void
    {
        $columns = [
            ['name' => 'column1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'Column2', 'definition' => ['type' => 'INTEGER']],
        ];

        $result = PatternHelper::transformColumnsCase($columns, true);

        $this->assertEquals([
            ['name' => 'COLUMN1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'COLUMN2', 'definition' => ['type' => 'INTEGER']],
        ], $result);
    }

    public function testTransformColumnsCaseWithLowercase(): void
    {
        $columns = [
            ['name' => 'COLUMN1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'Column2', 'definition' => ['type' => 'INTEGER']],
        ];

        $result = PatternHelper::transformColumnsCase($columns, false);

        $this->assertEquals([
            ['name' => 'column1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'column2', 'definition' => ['type' => 'INTEGER']],
        ], $result);
    }

    public function testMergeColumnsWithDefinition(): void
    {
        $columns1 = [
            ['name' => 'col1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'col2', 'definition' => ['type' => 'INTEGER']],
        ];

        $columns2 = [
            ['name' => 'col3', 'definition' => ['type' => 'DATE']],
            ['name' => 'col1', 'definition' => ['type' => 'TEXT']], // Duplicate name
        ];

        $result = PatternHelper::mergeColumnsWithDefinition($columns1, $columns2);

        $this->assertEquals([
            ['name' => 'col1', 'definition' => ['type' => 'TEXT']], // Last one wins
            ['name' => 'col2', 'definition' => ['type' => 'INTEGER']],
            ['name' => 'col3', 'definition' => ['type' => 'DATE']],
        ], $result);
    }

    public function testMergeColumnsWithDefinitionSingleArray(): void
    {
        $columns = [
            ['name' => 'col1', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'col2', 'definition' => ['type' => 'INTEGER']],
        ];

        $result = PatternHelper::mergeColumnsWithDefinition($columns);

        $this->assertEquals($columns, $result);
    }

    public function testMergeColumnsWithDefinitionEmptyArrays(): void
    {
        $result = PatternHelper::mergeColumnsWithDefinition([], []);

        $this->assertEquals([], $result);
    }

    /**
     * @dataProvider noIndentProvider
     */
    public function testNoIndent(string $input, string $expected): void
    {
        $result = PatternHelper::noIndent($input);

        $this->assertEquals($expected, $result);
    }

    public function noIndentProvider(): \Generator
    {
        yield 'multiple lines with indentation' => [
            "    line1\n    line2\n        line3\n    line4",
            "line1\nline2\nline3\nline4",
        ];

        yield 'with empty lines' => [
            "    line1\n\n    line2\n    \n    line3",
            "line1\n\nline2\n\nline3",
        ];

        yield 'single line' => [
            '    single line',
            'single line',
        ];

        yield 'empty string' => [
            '',
            '',
        ];
    }
}
