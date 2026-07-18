<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Indexer\Stock\Strategy;

use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryIndexer\Indexer\Stock\GetAllStockIds;
use Magento\InventoryIndexer\Indexer\Stock\Strategy\Async;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AsyncTest extends TestCase
{
    private const TOPIC = 'inventory.indexer.stock';

    /**
     * @var PublisherInterface|MockObject
     */
    private $publisher;

    /**
     * @var GetAllStockIds|MockObject
     */
    private $getAllStockIds;

    /**
     * @var Async
     */
    private $strategy;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(PublisherInterface::class);
        $this->getAllStockIds = $this->createMock(GetAllStockIds::class);
        $this->strategy = new Async($this->getAllStockIds, $this->publisher);
    }

    public function testExecuteListPublishesOneMessagePerStock(): void
    {
        $published = [];
        $this->publisher->method('publish')
            ->willReturnCallback(function (string $topic, array $stockIds) use (&$published) {
                $published[] = [$topic, $stockIds];
            });

        $this->strategy->executeList(['2', 3, '5']);

        self::assertSame(
            [
                [self::TOPIC, [2]],
                [self::TOPIC, [3]],
                [self::TOPIC, [5]],
            ],
            $published
        );
    }

    public function testExecuteFullPublishesOneMessagePerStock(): void
    {
        $this->getAllStockIds->method('execute')->willReturn([1, 2]);

        $published = [];
        $this->publisher->method('publish')
            ->willReturnCallback(function (string $topic, array $stockIds) use (&$published) {
                $published[] = $stockIds;
            });

        $this->strategy->executeFull();

        self::assertSame([[1], [2]], $published);
    }

    public function testExecuteRowPublishesSingleStock(): void
    {
        $this->publisher->expects(self::once())
            ->method('publish')
            ->with(self::TOPIC, [7]);

        $this->strategy->executeRow(7);
    }
}
