<?php

declare(strict_types=1);

namespace Keboola\TransformationPatternScd\Tests;

use Keboola\StorageApi\Client;
use Keboola\TransformationPatternScd\ApiFacade;
use Keboola\TransformationPatternScd\Mapping\Table;
use Keboola\TransformationPatternScd\Parameters\Parameters;
use Keboola\TransformationPatternScd\Patterns\Scd2Pattern;
use PHPUnit\Framework\TestCase;

class ApiFacadeTest extends TestCase
{
    public function testCreateSnapshotTableKeepsColumnDtypes(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('bucketExists')
            ->with('out.c-bucket')
            ->willReturn(true);
        $client->expects($this->once())
            ->method('tableExists')
            ->with('out.c-bucket.new_snapshot')
            ->willReturn(false);
        $client->expects($this->once())
            ->method('createTableDefinition')
            ->with(
                'out.c-bucket',
                $this->callback(function (array $payload): bool {
                    $this->assertSame('new_snapshot', $payload['name']);
                    $this->assertSame([
                        ['name' => 'id', 'dataType' => ['base' => 'INTEGER']],
                        ['name' => 'amount', 'dataType' => ['base' => 'NUMBER']],
                        ['name' => 'active', 'dataType' => ['base' => 'BOOLEAN']],
                        ['name' => 'start_date', 'definition' => ['type' => 'DATE']],
                        ['name' => 'end_date', 'definition' => ['type' => 'DATE']],
                    ], $payload['columns']);
                    return true;
                })
            );

        $apiFacade = new ApiFacade($client, '/tmp');

        $snapshotTable = new Table([
            'source' => 'out.c-bucket.new_snapshot',
            'destination' => 'current_snapshot',
        ], Table::MAPPING_TYPE_INPUT);

        $inTableDetail = [
            'isTyped' => true,
            'definition' => [
                'columns' => [
                    [
                        'name' => 'id',
                        'dataType' => ['base' => 'INTEGER'],
                        'canBeFiltered' => true,
                    ],
                    [
                        'name' => 'amount',
                        'dataType' => ['base' => 'NUMBER'],
                        'canBeFiltered' => false,
                    ],
                    [
                        'name' => 'active',
                        'dataType' => ['base' => 'BOOLEAN'],
                        'canBeFiltered' => false,
                    ],
                ],
            ],
        ];

        // Create mock pattern object
        $parameters = $this->createMock(Parameters::class);
        $parameters->method('getUppercaseColumns')->willReturn(false);
        $parameters->method('useDatetime')->willReturn(false);
        $parameters->method('getDeletedFlagValue')->willReturn(['0', '1']);

        $pattern = $this->createMock(Scd2Pattern::class);
        $pattern->method('getParameters')->willReturn($parameters);
        $pattern->method('getSnapshotSpecialColumns')->willReturn(['start_date', 'end_date']);

        $apiFacade->createSnapshotTable(
            $snapshotTable,
            $inTableDetail,
            $pattern
        );
    }
}
