<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOversoldSourceItems;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetOversoldSourceItemsTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var GetOversoldSourceItems
     */
    private $getOversoldSourceItems;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'group', 'having', 'joinLeft', 'limit'] as $method) {
            $select->method($method)->willReturnSelf();
        }

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->getOversoldSourceItems = new GetOversoldSourceItems($resourceConnection);
    }

    public function testMapsRowsToOversoldEntries(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            ['source_code' => 'src-a', 'sku' => 'sku-1', 'reserved' => '-5', 'physical' => '2'],
            ['source_code' => 'src-b', 'sku' => 'sku-2', 'reserved' => '-3', 'physical' => '0'],
        ]);

        $result = $this->getOversoldSourceItems->execute(100);

        self::assertCount(2, $result);
        self::assertSame('src-a', $result[0]['source_code']);
        self::assertSame(2.0, $result[0]['physical']);
        self::assertSame(-5.0, $result[0]['reserved']);
        self::assertSame(-3.0, $result[0]['delta']);
        self::assertSame(-3.0, $result[1]['delta']);
    }

    public function testReturnsEmptyWhenNoRows(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        self::assertSame([], $this->getOversoldSourceItems->execute(100));
    }
}
