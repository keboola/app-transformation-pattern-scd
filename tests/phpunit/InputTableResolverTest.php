<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use Generator;
use Keboola\Component\UserException;
use Keboola\TransformationPatternScd\ApiFacade;
use Keboola\TransformationPatternScd\Config;
use Keboola\TransformationPatternScd\InputTableResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InputTableResolverTest extends TestCase
{
    /** @var Config&MockObject */
    private $config;
    /** @var ApiFacade&MockObject */
    private $apiFacade;
    private InputTableResolver $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->apiFacade = $this->createMock(ApiFacade::class);
        $this->resolver = new InputTableResolver($this->config, $this->apiFacade);
    }

    public function testGetInputTableIdWithSingleTable(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([
            ['source' => 'in.c-main.test_table'],
        ]);

        $result = $this->resolver->getInputTableId();

        self::assertEquals('in.c-main.test_table', $result);
    }

    public function testGetInputTableIdWithMultipleTables(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([
            ['source' => 'in.c-main.table1', 'destination' => 'other_table'],
            ['source' => 'in.c-main.table2', 'destination' => 'input_table'],
            ['source' => 'in.c-main.table3', 'destination' => 'another_table'],
        ]);

        $result = $this->resolver->getInputTableId();

        self::assertEquals('in.c-main.table2', $result);
    }

    public function testGetInputTableIdWithEmptyTables(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([]);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Please specify one input table in the input mapping.');

        $this->resolver->getInputTableId();
    }

    public function testGetInputTableIdWithMultipleTablesNoInputTable(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([
            ['source' => 'in.c-main.table1', 'destination' => 'other_table'],
            ['source' => 'in.c-main.table2', 'destination' => 'another_table'],
        ]);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'Found "2" tables in input mapping, but no source table with "destination" = "input_table". ' .
            'Please set the source table in the input mapping.'
        );

        $this->resolver->getInputTableId();
    }

    public function testGetInputTableColumns(): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn([
            'columns' => ['id', 'name', 'email', 'phone'],
        ]);

        $result = $this->resolver->getInputTableColumns();

        self::assertEquals(['id', 'name', 'email', 'phone'], $result);
    }

    public function testGetInputTableDefinition(): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn([
            'definition' => [
                'columns' => [
                    ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                    ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
                ],
            ],
        ]);

        $result = $this->resolver->getInputTableDefinition();

        self::assertEquals([
            'columns' => [
                ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
            ],
        ], $result);
    }

    public function testGetInputTableDefinitionWithoutDefinition(): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn([
            'columns' => ['id', 'name'],
        ]);

        $result = $this->resolver->getInputTableDefinition();

        self::assertEquals([], $result);
    }

    public function testCachingOfTableDetail(): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn([
            'columns' => ['id', 'name'],
            'definition' => ['columns' => []],
        ]);

        // Call multiple methods that use getInputTableDetail
        $this->resolver->getInputTableColumns();
        $this->resolver->getInputTableDefinition();
        $this->resolver->getInputTableColumns(); // Should not call getTable again
    }

    /**
     * @dataProvider inputTablesProvider
     */
    public function testGetInputTableIdWithDataProvider(
        array $inputTables,
        string $expectedTableId,
        bool $shouldThrowException,
        string $expectedExceptionMessage = ''
    ): void {
        $this->config->expects($this->once())->method('getInputTables')->willReturn($inputTables);

        if ($shouldThrowException) {
            $this->expectException(UserException::class);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $result = $this->resolver->getInputTableId();

        if (!$shouldThrowException) {
            self::assertEquals($expectedTableId, $result);
        }
    }

    public function inputTablesProvider(): Generator
    {
        yield 'single table' => [
            'inputTables' => [['source' => 'in.c-main.single']],
            'expectedTableId' => 'in.c-main.single',
            'shouldThrowException' => false,
        ];

        yield 'multiple tables with input_table destination' => [
            'inputTables' => [
                ['source' => 'in.c-main.table1', 'destination' => 'other'],
                ['source' => 'in.c-main.table2', 'destination' => 'input_table'],
            ],
            'expectedTableId' => 'in.c-main.table2',
            'shouldThrowException' => false,
        ];

        yield 'multiple tables without input_table destination' => [
            'inputTables' => [
                ['source' => 'in.c-main.table1', 'destination' => 'other1'],
                ['source' => 'in.c-main.table2', 'destination' => 'other2'],
            ],
            'expectedTableId' => '',
            'shouldThrowException' => true,
            'expectedExceptionMessage' => 'Found "2" tables in input mapping, but no source table with ' .
                '"destination" = "input_table". Please set the source table in the input mapping.',
        ];

        yield 'empty tables' => [
            'inputTables' => [],
            'expectedTableId' => '',
            'shouldThrowException' => true,
            'expectedExceptionMessage' => 'Please specify one input table in the input mapping.',
        ];

        yield 'table without destination' => [
            'inputTables' => [
                ['source' => 'in.c-main.table1'],
                ['source' => 'in.c-main.table2', 'destination' => 'input_table'],
            ],
            'expectedTableId' => 'in.c-main.table2',
            'shouldThrowException' => false,
        ];
    }

    private function setupConfigWithSingleTable(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([
            ['source' => 'in.c-main.test_table'],
        ]);
    }
}
