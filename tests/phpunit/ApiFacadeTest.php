<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use Generator;
use Keboola\Component\UserException;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\TransformationPatternScd\ApiFacade;
use Keboola\TransformationPatternScd\Mapping\Table;
use Keboola\TransformationPatternScd\Patterns\Pattern;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ApiFacadeTest extends TestCase
{
    /** @var Client&MockObject */
    private $client;
    private string $dataDir;
    private ApiFacade $apiFacade;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->dataDir = sys_get_temp_dir() . '/test-data';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
        $this->apiFacade = new ApiFacade($this->client, $this->dataDir);
    }

    /**
     * @dataProvider nativeTypesProvider
     */
    public function testCreateSnapshotTableWithNativeTypesFeatures(array $features): void
    {
        /** @var Table&MockObject $snapshotTable */
        $snapshotTable = $this->createMock(Table::class);
        /** @var Pattern&MockObject $pattern */
        $pattern = $this->createMock(Pattern::class);

        $snapshotTable->expects($this->atLeastOnce())->method('getTableId')->willReturn('out.c-test-bucket.snapshot-table');
        $snapshotTable->expects($this->exactly(2))->method('getBuckedId')->willReturn('out.c-test-bucket');
        $snapshotTable->expects($this->once())->method('getTableName')->willReturn('snapshot-table');

        $pattern->expects($this->once())->method('getSnapshotPrimaryKey')->willReturn('snapshot_pk');
        $pattern->expects($this->once())->method('getSnapshotTypedColumns')->willReturn([
            ['name' => 'snapshot_pk', 'definition' => ['type' => 'VARCHAR']],
            ['name' => 'start_date', 'definition' => ['type' => 'DATE']],
        ]);

        // Mock table exists check
        $this->client->expects($this->once())->method('tableExists')->willReturn(false);

        // Mock bucket exists check
        $this->client->expects($this->once())->method('bucketExists')->willReturn(true);

        // Mock verifyToken with native types features
        $this->client->expects($this->once())->method('verifyToken')->willReturn([
            'owner' => [
                'features' => $features,
            ],
        ]);

        // Expect createTableDefinition to be called (when missing native types)
        $this->client->expects($this->once())
            ->method('createTableDefinition')
            ->with(
                'out.c-test-bucket',
                [
                    'name' => 'snapshot-table',
                    'primaryKeysNames' => ['snapshot_pk'],
                    'columns' => [
                        ['name' => 'snapshot_pk', 'definition' => ['type' => 'VARCHAR']],
                        ['name' => 'start_date', 'definition' => ['type' => 'DATE']],
                    ],
                ]
            );

        $this->apiFacade->createSnapshotTable($snapshotTable, $pattern);
    }

    public function testCreateSnapshotTableWithoutNativeTypesFeatures(): void
    {
        /** @var Table&MockObject $snapshotTable */
        $snapshotTable = $this->createMock(Table::class);
        /** @var Pattern&MockObject $pattern */
        $pattern = $this->createMock(Pattern::class);

        $snapshotTable->expects($this->atLeastOnce())->method('getTableId')->willReturn('out.c-test-bucket.snapshot-table');
        $snapshotTable->expects($this->exactly(2))->method('getBuckedId')->willReturn('out.c-test-bucket');
        $snapshotTable->expects($this->once())->method('getTableName')->willReturn('snapshot-table');

        $pattern->expects($this->once())->method('getSnapshotPrimaryKey')->willReturn('snapshot_pk');
        $pattern->expects($this->once())->method('getSnapshotTableHeader')->willReturn(['snapshot_pk', 'start_date']);

        // Mock table exists check
        $this->client->expects($this->once())->method('tableExists')->willReturn(false);

        // Mock bucket exists check
        $this->client->expects($this->once())->method('bucketExists')->willReturn(true);

        // Mock verifyToken without native types features
        $this->client->expects($this->once())->method('verifyToken')->willReturn([
            'owner' => [
                'features' => ['other-feature'],
            ],
        ]);

        // Expect createTableAsync to be called (when has native types)
        $this->client->expects($this->once())
            ->method('createTableAsync')
            ->with(
                'out.c-test-bucket',
                'snapshot-table',
                $this->isInstanceOf(CsvFile::class),
                ['primaryKey' => 'snapshot_pk']
            );

        $this->apiFacade->createSnapshotTable($snapshotTable, $pattern);
    }

    public function testCreateSnapshotTableWhenTableExists(): void
    {
        /** @var Table&MockObject $snapshotTable */
        $snapshotTable = $this->createMock(Table::class);
        /** @var Pattern&MockObject $pattern */
        $pattern = $this->createMock(Pattern::class);

        $snapshotTable->expects($this->atLeastOnce())->method('getTableId')->willReturn('out.c-test-bucket.snapshot-table');

        // Mock table exists check - table already exists
        $this->client->expects($this->once())->method('tableExists')->willReturn(true);

        // Expect no table creation methods to be called
        $this->client->expects($this->never())->method('createTableDefinition');
        $this->client->expects($this->never())->method('createTableAsync');
        $this->client->expects($this->never())->method('createBucket');

        $this->apiFacade->createSnapshotTable($snapshotTable, $pattern);
    }

    /**
     * @dataProvider nativeTypesProvider
     */
    public function testCreateSnapshotTableWithClientException(array $features): void
    {
        /** @var Table&MockObject $snapshotTable */
        $snapshotTable = $this->createMock(Table::class);
        /** @var Pattern&MockObject $pattern */
        $pattern = $this->createMock(Pattern::class);

        $snapshotTable->expects($this->atLeastOnce())->method('getTableId')->willReturn('out.c-test-bucket.snapshot-table');
        $snapshotTable->expects($this->exactly(2))->method('getBuckedId')->willReturn('out.c-test-bucket');
        $snapshotTable->expects($this->once())->method('getTableName')->willReturn('snapshot-table');

        $pattern->expects($this->once())->method('getSnapshotPrimaryKey')->willReturn('snapshot_pk');
        $pattern->expects($this->once())->method('getSnapshotTypedColumns')->willReturn([]);

        // Mock table exists check
        $this->client->expects($this->once())->method('tableExists')->willReturn(false);

        // Mock bucket exists check
        $this->client->expects($this->once())->method('bucketExists')->willReturn(true);

        // Mock verifyToken
        $this->client->expects($this->once())->method('verifyToken')->willReturn([
            'owner' => [
                'features' => $features,
            ],
        ]);

        // Mock createTableDefinition to throw exception
        $clientException = new ClientException('Table creation failed');
        $this->client->expects($this->once())->method('createTableDefinition')->willThrowException($clientException);

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'Cannot create snapshot table "out.c-test-bucket.snapshot-table": Table creation failed'
        );

        $this->apiFacade->createSnapshotTable($snapshotTable, $pattern);
    }

    public function testGetTable(): void
    {
        $tableId = 'out.c-test-bucket.test-table';
        $expectedTableData = [
            'id' => $tableId,
            'name' => 'test-table',
            'columns' => ['id', 'name'],
        ];

        $this->client->expects($this->once())
            ->method('getTable')
            ->with($tableId)
            ->willReturn($expectedTableData);

        $result = $this->apiFacade->getTable($tableId);

        $this->assertEquals($expectedTableData, $result);
    }

    /**
     * @dataProvider nativeTypesProvider
     */
    public function testCreateSnapshotTableCreatesBucketWhenNotExists(array $features): void
    {
        /** @var Table&MockObject $snapshotTable */
        $snapshotTable = $this->createMock(Table::class);
        /** @var Pattern&MockObject $pattern */
        $pattern = $this->createMock(Pattern::class);

        $snapshotTable->expects($this->atLeastOnce())->method('getTableId')->willReturn('out.c-test-bucket.snapshot-table');
        $snapshotTable->expects($this->exactly(2))->method('getBuckedId')->willReturn('out.c-test-bucket');
        $snapshotTable->expects($this->once())->method('getTableName')->willReturn('snapshot-table');

        $pattern->expects($this->once())->method('getSnapshotPrimaryKey')->willReturn('snapshot_pk');
        $pattern->expects($this->once())->method('getSnapshotTypedColumns')->willReturn([]);

        // Mock table exists check
        $this->client->expects($this->once())->method('tableExists')->willReturn(false);

        // Mock bucket exists check - bucket does not exist
        $this->client->expects($this->once())->method('bucketExists')->willReturn(false);

        // Mock verifyToken
        $this->client->expects($this->once())->method('verifyToken')->willReturn([
            'owner' => [
                'features' => $features,
            ],
        ]);

        // Expect bucket creation
        $this->client->expects($this->once())
            ->method('createBucket')
            ->with('test-bucket', 'out');

        // Expect table creation
        $this->client->expects($this->once())
            ->method('createTableDefinition');

        $this->apiFacade->createSnapshotTable($snapshotTable, $pattern);
    }

    public function nativeTypesProvider(): Generator
    {
        yield 'with native-types feature' => [['native-types']];
        yield 'with new-native-types feature' => [['new-native-types']];
    }
}
