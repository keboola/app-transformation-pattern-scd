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


    public function testGetInputTableColumns(): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn([
            'columns' => ['id', 'name', 'email', 'phone'],
        ]);

        $result = $this->resolver->getInputTableColumns();

        self::assertEquals(['id', 'name', 'email', 'phone'], $result);
    }

    /**
     * @dataProvider tableDefinitionProvider
     */
    public function testGetInputTableDefinition(array $tableData, array $expectedDefinition): void
    {
        $this->setupConfigWithSingleTable();
        $this->apiFacade->expects($this->once())->method('getTable')->willReturn($tableData);

        $result = $this->resolver->getInputTableDefinition();

        self::assertEquals($expectedDefinition, $result);
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

    public function tableDefinitionProvider(): Generator
    {
        yield 'with definition' => [
            'tableData' => [
                'definition' => [
                    'columns' => [
                        ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                        ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
                    ],
                ],
            ],
            'expectedDefinition' => [
                'columns' => [
                    ['name' => 'id', 'definition' => ['type' => 'VARCHAR']],
                    ['name' => 'name', 'definition' => ['type' => 'VARCHAR']],
                ],
            ],
        ];

        yield 'without definition' => [
            'tableData' => [
                'columns' => ['id', 'name'],
            ],
            'expectedDefinition' => [],
        ];
    }

    private function setupConfigWithSingleTable(): void
    {
        $this->config->expects($this->once())->method('getInputTables')->willReturn([
            ['source' => 'in.c-main.test_table'],
        ]);
    }
}
