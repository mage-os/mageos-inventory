<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventorySales;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Model\GetSkusRequiringReservation;
use Magento\InventorySales\Model\ResourceModel\AcquireInventoryLock;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;

/**
 * Hold the inventory lock of the reserved SKUs while a sale checks quantities and writes its reservations.
 */
class LockReservedSkusPlugin
{
    /**
     * @param GetSkusRequiringReservation $getSkusRequiringReservation
     * @param AcquireInventoryLock $acquireInventoryLock
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     */
    public function __construct(
        private readonly GetSkusRequiringReservation $getSkusRequiringReservation,
        private readonly AcquireInventoryLock $acquireInventoryLock,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
    ) {
    }

    /**
     * Lock the SKUs the sale reserves around the quantity check and the reservation write.
     *
     * @param AppendReservations $subject
     * @param callable $proceed
     * @param int $websiteId
     * @param array $itemsBySku
     * @param mixed $order
     * @param array $itemsToSell
     * @return array
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundReserve(
        AppendReservations $subject,
        callable $proceed,
        $websiteId,
        $itemsBySku,
        $order,
        $itemsToSell
    ) {
        $stockId = (int)$this->stockByWebsiteIdResolver->execute((int)$websiteId)->getStockId();
        $skusWithDemand = $this->getSkusWithDemand($itemsToSell);
        $skus = $skusWithDemand ? $this->getSkusRequiringReservation->execute($skusWithDemand, $stockId) : [];
        sort($skus, SORT_STRING);

        $lockedSkus = [];
        try {
            foreach ($skus as $sku) {
                if (!$this->acquireInventoryLock->execute($sku, $stockId)) {
                    throw new LocalizedException(
                        __('Could not acquire inventory lock for SKU: %1. Please try again.', $sku)
                    );
                }
                $lockedSkus[] = $sku;
            }

            return $proceed($websiteId, $itemsBySku, $order, $itemsToSell);
        } finally {
            foreach ($lockedSkus as $sku) {
                $this->acquireInventoryLock->release($sku, $stockId);
            }
        }
    }

    /**
     * SKUs whose reservations take stock away, which are the only ones a concurrent sale can oversell.
     *
     * @param ItemToSellInterface[] $itemsToSell
     * @return array
     */
    private function getSkusWithDemand(array $itemsToSell): array
    {
        $netBySku = [];
        foreach ($itemsToSell as $item) {
            $netBySku[$item->getSku()] = ($netBySku[$item->getSku()] ?? 0.0) + $item->getQuantity();
        }

        return array_keys(array_filter($netBySku, static fn(float $net) => $net < 0));
    }
}
