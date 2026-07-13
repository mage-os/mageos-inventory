<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Quote;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySales\Model\ReservationExecutionInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySales\Plugin\Quote\CartManagementPlugin;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CartManagementPluginTest extends TestCase
{
    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIds;

    /**
     * @var AcquireStockItemLocks|MockObject
     */
    private $acquireStockItemLocks;

    /**
     * @var StockByWebsiteIdResolverInterface|MockObject
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var ReservationExecutionInterface|MockObject
     */
    private $reservationExecution;

    /**
     * @var CartManagementPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $this->acquireStockItemLocks = $this->createMock(AcquireStockItemLocks::class);
        $this->stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->reservationExecution = $this->createMock(ReservationExecutionInterface::class);

        $this->plugin = new CartManagementPlugin(
            $this->getSkusByProductIds,
            $this->acquireStockItemLocks,
            $this->stockByWebsiteIdResolver,
            $this->storeManager,
            $this->reservationExecution
        );
    }

    public function testAroundSubmitLocksStockItemsAndReleases(): void
    {
        $subject = $this->createMock(QuoteManagement::class);
        $order = $this->createMock(OrderInterface::class);

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $item = new class {
            public function getProductId(): int
            {
                return 42;
            }
        };
        $quote->method('getAllVisibleItems')->willReturn([$item]);

        $store = $this->createConfiguredMock(StoreInterface::class, ['getWebsiteId' => 2]);
        $stock = $this->createConfiguredMock(StockInterface::class, ['getStockId' => 3]);

        $this->reservationExecution->method('isDeferred')->willReturn(true);
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->stockByWebsiteIdResolver->method('execute')->with(2)->willReturn($stock);
        $this->getSkusByProductIds->method('execute')->with([42])->willReturn(['simple-1']);
        $this->acquireStockItemLocks->expects($this->once())->method('execute')->with(['simple-1'], 3);
        $this->acquireStockItemLocks->expects($this->once())->method('releaseAll');

        $proceed = function (Quote $received, array $orderData) use ($quote, $order): OrderInterface {
            $this->assertSame($quote, $received);
            $this->assertSame([], $orderData);
            return $order;
        };

        $result = $this->plugin->aroundSubmit($subject, $proceed, $quote);
        $this->assertSame($order, $result);
    }

    public function testAroundSubmitSkipsLockingWhenNotDeferred(): void
    {
        $subject = $this->createMock(QuoteManagement::class);
        $order = $this->createMock(OrderInterface::class);
        $quote = $this->createMock(Quote::class);

        $this->reservationExecution->method('isDeferred')->willReturn(false);
        $this->acquireStockItemLocks->expects($this->never())->method('execute');
        $this->acquireStockItemLocks->expects($this->never())->method('releaseAll');
        $quote->expects($this->never())->method('getAllVisibleItems');

        $proceed = static function (Quote $received, array $orderData) use ($order): OrderInterface {
            return $order;
        };

        $result = $this->plugin->aroundSubmit($subject, $proceed, $quote);
        $this->assertSame($order, $result);
    }

    public function testAroundSubmitReleasesLocksOnFailure(): void
    {
        $subject = $this->createMock(QuoteManagement::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $item = new class {
            public function getProductId(): int
            {
                return 7;
            }
        };
        $quote->method('getAllVisibleItems')->willReturn([$item]);

        $store = $this->createConfiguredMock(StoreInterface::class, ['getWebsiteId' => 2]);
        $stock = $this->createConfiguredMock(StockInterface::class, ['getStockId' => 3]);

        $this->reservationExecution->method('isDeferred')->willReturn(true);
        $this->storeManager->method('getStore')->with(1)->willReturn($store);
        $this->stockByWebsiteIdResolver->method('execute')->with(2)->willReturn($stock);
        $this->getSkusByProductIds->method('execute')->with([7])->willReturn(['simple-7']);
        $this->acquireStockItemLocks->expects($this->once())->method('execute')->with(['simple-7'], 3);
        $this->acquireStockItemLocks->expects($this->once())->method('releaseAll');

        $proceed = static function (): void {
            throw new \RuntimeException('submit failed');
        };

        $this->expectException(\RuntimeException::class);
        $this->plugin->aroundSubmit($subject, $proceed, $quote);
    }
}
