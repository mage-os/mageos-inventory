<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Sales\OrderManagement;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\ReservationExecutionInterface;
use Magento\InventorySales\Plugin\Sales\OrderManagement\AppendReservationsAfterOrderPlacementPlugin;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventExtensionInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

class AppendReservationsAfterOrderPlacementPluginTest extends TestCase
{
    public function testReservesTheChildSimplesOfCompositeItemsAndNotTheirParents(): void
    {
        $skusById = [10 => 'sku_config', 11 => 'sku_s', 20 => 'sku_bundle', 30 => 'sku_free'];
        $types = [
            'sku_config' => 'configurable',
            'sku_s' => 'simple',
            'sku_bundle' => 'bundle',
            'sku_free' => 'simple',
        ];
        $order = $this->order([[10, 1.0], [11, 1.0], [20, 1.0], [30, 2.0], [11, 1.0]]);

        $getSkusByProductIds = $this->createStub(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->willReturnCallback(
            fn(array $ids) => array_intersect_key($skusById, array_flip($ids))
        );
        $getProductTypesBySkus = $this->createConfiguredStub(
            GetProductTypesBySkusInterface::class,
            ['execute' => $types]
        );
        $isSourceItemManagementAllowed = $this->createStub(IsSourceItemManagementAllowedForProductTypeInterface::class);
        $isSourceItemManagementAllowed->method('execute')->willReturnCallback(fn(string $type) => $type === 'simple');
        $itemsToSellFactory = $this->createStub(ItemToSellInterfaceFactory::class);
        $itemsToSellFactory->method('create')->willReturnCallback(
            fn(array $data) => $this->createConfiguredStub(
                ItemToSellInterface::class,
                ['getSku' => $data['sku'], 'getQuantity' => $data['qty']]
            )
        );

        $reserved = [];
        $appendReservations = $this->createMock(AppendReservations::class);
        $appendReservations->expects($this->once())->method('reserve')->willReturnCallback(
            function ($websiteId, $itemsBySku, $receivedOrder, $itemsToSell) use (&$reserved) {
                $reserved = [$websiteId, $itemsBySku, $itemsToSell];
                return [
                    $this->createStub(SalesChannelInterface::class),
                    $this->createStub(SalesEventExtensionInterface::class),
                ];
            }
        );

        $plugin = new AppendReservationsAfterOrderPlacementPlugin(
            $this->createStub(PlaceReservationsForSalesEventInterface::class),
            $getSkusByProductIds,
            $this->createStub(SalesEventInterfaceFactory::class),
            $itemsToSellFactory,
            $getProductTypesBySkus,
            $isSourceItemManagementAllowed,
            $appendReservations,
            $this->createConfiguredStub(ReservationExecutionInterface::class, ['isDeferred' => true])
        );
        $plugin->aroundPlace($this->createStub(OrderManagementInterface::class), fn($received) => $received, $order);

        [$websiteId, $itemsBySku, $itemsToSell] = $reserved;
        $this->assertSame(3, $websiteId);
        $this->assertSame(['sku_s' => 2.0, 'sku_free' => 2.0], $itemsBySku);
        $this->assertSame(
            ['sku_s' => -2.0, 'sku_free' => -2.0],
            array_combine(
                array_map(fn($item) => $item->getSku(), $itemsToSell),
                array_map(fn($item) => $item->getQuantity(), $itemsToSell)
            )
        );
    }

    /**
     * @param array<int,array{int,float}> $lines
     * @return Order
     */
    private function order(array $lines): Order
    {
        $items = [];
        foreach ($lines as [$productId, $qty]) {
            $items[] = $this->createConfiguredStub(
                OrderItemInterface::class,
                ['getProductId' => $productId, 'getQtyOrdered' => $qty]
            );
        }

        return $this->createConfiguredStub(
            Order::class,
            ['getItems' => $items, 'getStore' => $this->createConfiguredStub(Store::class, ['getWebsiteId' => 3])]
        );
    }
}
