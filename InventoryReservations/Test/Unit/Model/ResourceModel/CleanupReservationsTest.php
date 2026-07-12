<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryReservations\Model\ResourceModel\CleanupReservations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CleanupReservationsTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var CleanupReservations
     */
    private $model;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $select = $this->createMock(Select::class);
        foreach (['from', 'group', 'having'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->model = new CleanupReservations($resourceConnection, 1024);
    }

    public function testDeletesReservationsInChunks(): void
    {
        $orderGroups = [];
        for ($id = 1; $id <= 25000; $id += 2) {
            $orderGroups[] = $id . ',' . ($id + 1);
        }
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls($orderGroups, []);

        $deletedChunks = [];
        $this->connection->method('delete')
            ->willReturnCallback(function (string $table, array $condition) use (&$deletedChunks) {
                $deletedChunks[] = current($condition);
                return 0;
            });

        $this->model->execute();

        self::assertSame([10000, 10000, 5000], array_map('count', $deletedChunks));
        self::assertSame(range(1, 25000), array_merge(...$deletedChunks));
    }

    public function testDoesNotDeleteWhenThereAreNoCompensatedReservations(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);

        $this->connection->expects(self::never())->method('delete');

        $this->model->execute();
    }

    public function testDeletesDuplicatedReservationIdsOnlyOnce(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(
            ['1,2,3'],
            ['3,4']
        );

        $deletedChunks = [];
        $this->connection->method('delete')
            ->willReturnCallback(function (string $table, array $condition) use (&$deletedChunks) {
                $deletedChunks[] = current($condition);
                return 0;
            });

        $this->model->execute();

        self::assertSame([[1, 2, 3, 4]], $deletedChunks);
    }

    public function testDoesNotSplitACompensatedGroupAcrossDeleteStatements(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(
            [implode(',', range(1, 9999)), '10000,10001,10002'],
            []
        );

        $deletedChunks = [];
        $this->connection->method('delete')
            ->willReturnCallback(function (string $table, array $condition) use (&$deletedChunks) {
                $deletedChunks[] = current($condition);
                return 0;
            });

        $this->model->execute();

        self::assertSame([9999, 3], array_map('count', $deletedChunks));
        self::assertSame([10000, 10001, 10002], end($deletedChunks));
    }

    public function testGroupsCompensatedReservationsBySourceCode(): void
    {
        $connection = $this->createMock(AdapterInterface::class);

        $groupCalls = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('having')->willReturnSelf();
        $select->method('group')
            ->willReturnCallback(function (...$columns) use (&$groupCalls, $select) {
                $groupCalls[] = $columns;
                return $select;
            });
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([]);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        (new CleanupReservations($resourceConnection, 1024))->execute();

        self::assertCount(2, $groupCalls);
        foreach ($groupCalls as $columns) {
            self::assertContains('source_code', $columns);
        }
    }

    public function testKeepsGroupLargerThanChunkSizeInASingleDeleteStatement(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(
            [implode(',', range(1, 10001))],
            []
        );

        $deletedChunks = [];
        $this->connection->method('delete')
            ->willReturnCallback(function (string $table, array $condition) use (&$deletedChunks) {
                $deletedChunks[] = current($condition);
                return 0;
            });

        $this->model->execute();

        self::assertSame([10001], array_map('count', $deletedChunks));
    }
}
