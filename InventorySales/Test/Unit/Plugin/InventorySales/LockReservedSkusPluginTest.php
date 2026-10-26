<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventorySales;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\GetSkusRequiringReservation;
use Magento\InventorySales\Model\ResourceModel\AcquireInventoryLock;
use Magento\InventorySales\Plugin\InventorySales\LockReservedSkusPlugin;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class LockReservedSkusPluginTest extends TestCase
{
    private const WEBSITE_ID = 1;

    private const STOCK_ID = 5;

    /**
     * @var string[]
     */
    private array $calls = [];

    /**
     * @var array<string,bool>
     */
    private array $manageStockBySku = [];

    /**
     * @var string[]
     */
    private array $unavailableLocks = [];

    /**
     * @var AcquireInventoryLock|Stub
     */
    private $acquireInventoryLock;

    /**
     * @var LockReservedSkusPlugin
     */
    private $plugin;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->acquireInventoryLock = $this->createStub(AcquireInventoryLock::class);
        $this->acquireInventoryLock->method('execute')->willReturnCallback(function (string $sku, int $stockId) {
            $this->calls[] = "lock:$sku@$stockId";
            return !in_array($sku, $this->unavailableLocks, true);
        });
        $this->acquireInventoryLock->method('release')->willReturnCallback(function (string $sku, int $stockId) {
            $this->calls[] = "release:$sku@$stockId";
            return true;
        });

        $stockConfiguration = $this->createConfiguredStub(
            StockConfigurationInterface::class,
            ['canSubtractQty' => true]
        );
        $getStockItemConfiguration = $this->createStub(GetStockItemConfigurationInterface::class);
        $getStockItemConfiguration->method('execute')->willReturnCallback(
            fn(string $sku) => $this->createConfiguredStub(
                StockItemConfigurationInterface::class,
                ['isManageStock' => $this->manageStockBySku[$sku] ?? true]
            )
        );

        $stock = $this->createConfiguredStub(StockInterface::class, ['getStockId' => self::STOCK_ID]);
        $stockResolver = $this->createStub(StockByWebsiteIdResolverInterface::class);
        $stockResolver->method('execute')->willReturnMap([[self::WEBSITE_ID, $stock]]);

        $this->plugin = new LockReservedSkusPlugin(
            new GetSkusRequiringReservation($getStockItemConfiguration, $stockConfiguration),
            $this->acquireInventoryLock,
            $stockResolver
        );
    }

    public function testLocksTheReservedSkusInSortedOrderOnlyWhileReserving(): void
    {
        $result = $this->aroundReserve($this->reserve(['channel', 'extension']), ['sku_b' => -1.0, 'sku_a' => -2.0]);

        $this->assertSame(['channel', 'extension'], $result);
        $this->assertSame(
            ['lock:sku_a@5', 'lock:sku_b@5', 'reserve', 'release:sku_a@5', 'release:sku_b@5'],
            $this->calls
        );
    }

    public function testPassesTheArgumentsThrough(): void
    {
        $order = new \stdClass();
        $itemsToSell = $this->itemsToSell(['sku_a' => -1.0]);
        $received = [];

        $this->plugin->aroundReserve(
            $this->createStub(AppendReservations::class),
            function (...$arguments) use (&$received) {
                $received = $arguments;
                return [];
            },
            self::WEBSITE_ID,
            ['sku_a' => 1.0],
            $order,
            $itemsToSell
        );

        $this->assertSame([self::WEBSITE_ID, ['sku_a' => 1.0], $order, $itemsToSell], $received);
    }

    public function testTakesNoLockForSkusWithoutManagedStock(): void
    {
        $this->manageStockBySku = ['sku_free' => false];

        $this->aroundReserve($this->reserve(), ['sku_free' => -1.0, 'sku_a' => -1.0]);

        $this->assertSame(['lock:sku_a@5', 'reserve', 'release:sku_a@5'], $this->calls);
    }

    public function testTakesNoLockForARestockingReservation(): void
    {
        $this->aroundReserve($this->reserve(), ['sku_a' => 1.0]);

        $this->assertSame(['reserve'], $this->calls);
    }

    public function testLocksOnlyTheSkusWithNetDemand(): void
    {
        $this->aroundReserve($this->reserve(), ['sku_a' => -2.0, 'sku_back' => 1.0]);

        $this->assertSame(['lock:sku_a@5', 'reserve', 'release:sku_a@5'], $this->calls);
    }

    public function testLocksNumericSkusAsStrings(): void
    {
        $this->aroundReserve($this->reserve(), ['123' => -1.0]);

        $this->assertSame(['lock:123@5', 'reserve', 'release:123@5'], $this->calls);
    }

    public function testFailsBeforeReservingWhenALockIsNotAcquired(): void
    {
        $this->unavailableLocks = ['sku_b'];

        try {
            $this->aroundReserve($this->reserve(), ['sku_b' => -1.0, 'sku_a' => -1.0]);
            $this->fail('Expected the reservation to be refused');
        } catch (LocalizedException $e) {
            $this->assertSame('Could not acquire inventory lock for SKU: sku_b. Please try again.', $e->getMessage());
        }

        $this->assertSame(['lock:sku_a@5', 'lock:sku_b@5', 'release:sku_a@5'], $this->calls);
    }

    public function testReleasesTheLocksWhenTheReservationFails(): void
    {
        $failure = new LocalizedException(__('Not enough items for sale'));

        try {
            $this->aroundReserve(static function () use ($failure) {
                throw $failure;
            }, ['sku_a' => -1.0]);
            $this->fail('Expected the reservation failure to propagate');
        } catch (LocalizedException $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(['lock:sku_a@5', 'release:sku_a@5'], $this->calls);
    }

    /**
     * @param array $result
     * @return callable
     */
    private function reserve(array $result = []): callable
    {
        return function () use ($result) {
            $this->calls[] = 'reserve';
            return $result;
        };
    }

    /**
     * @param callable $proceed
     * @param array<string,float> $quantityBySku
     * @return mixed
     */
    private function aroundReserve(callable $proceed, array $quantityBySku)
    {
        return $this->plugin->aroundReserve(
            $this->createStub(AppendReservations::class),
            $proceed,
            self::WEBSITE_ID,
            array_map('abs', $quantityBySku),
            new \stdClass(),
            $this->itemsToSell($quantityBySku)
        );
    }

    /**
     * @param array<string,float> $quantityBySku
     * @return ItemToSellInterface[]
     */
    private function itemsToSell(array $quantityBySku): array
    {
        $items = [];
        foreach ($quantityBySku as $sku => $quantity) {
            $items[] = $this->createConfiguredStub(
                ItemToSellInterface::class,
                ['getSku' => (string)$sku, 'getQuantity' => $quantity]
            );
        }

        return $items;
    }
}
