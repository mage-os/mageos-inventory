<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryStockVisualizer\Model\Cache\FlushStockVisualizerCache;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see PurgeBySkus
 */
class PurgeBySkusTest extends TestCase
{
    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkus;

    /**
     * @var FlushStockVisualizerCache|MockObject
     */
    private $flush;

    /**
     * @var PurgeBySkus
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getProductIdsBySkus = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->flush = $this->createMock(FlushStockVisualizerCache::class);

        $this->model = new PurgeBySkus($this->getProductIdsBySkus, $this->flush);
    }

    /**
     * Empty SKU list flushes nothing.
     *
     * @return void
     */
    public function testEmptyDoesNotFlush(): void
    {
        $this->flush->expects($this->never())->method('execute');

        $this->model->execute([]);
    }

    /**
     * Resolved product ids are flushed.
     *
     * @return void
     */
    public function testFlushesResolvedProductIds(): void
    {
        $this->getProductIdsBySkus->method('execute')
            ->with(['SKU-1', 'SKU-2'])
            ->willReturn(['SKU-1' => 11, 'SKU-2' => 22]);
        $this->flush->expects($this->once())->method('execute')->with([11, 22]);

        $this->model->execute(['SKU-1', 'SKU-2']);
    }

    /**
     * A missing SKU in the batch falls back to per-SKU resolution and skips the unknown one.
     *
     * @return void
     */
    public function testFallsBackWhenBatchResolutionFails(): void
    {
        $this->getProductIdsBySkus->method('execute')
            ->willReturnCallback(function (array $skus) {
                if (count($skus) > 1) {
                    throw new NoSuchEntityException(__('missing'));
                }
                if ($skus === ['SKU-1']) {
                    return ['SKU-1' => 11];
                }
                throw new NoSuchEntityException(__('missing'));
            });
        $this->flush->expects($this->once())->method('execute')->with([11]);

        $this->model->execute(['SKU-1', 'GONE']);
    }
}
