<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryReservationsApi;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableForRequestedQtyInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableForRequestedQtyRequestInterfaceFactory;

/**
 * Reject demand (negative) reservations that would oversell, enforced at the
 * single append chokepoint so it also covers paths that skip the salability
 * check (direct API, import). Salability is delegated to the place-order chain,
 * so backorders and min-qty are honoured and nothing that Magento already
 * considers sellable is ever rejected.
 */
class RejectOversellingReservationsPlugin
{
    /**
     * @param AreProductsSalableForRequestedQtyInterface $areProductsSalableForRequestedQty
     * @param IsProductSalableForRequestedQtyRequestInterfaceFactory $requestFactory
     */
    public function __construct(
        private readonly AreProductsSalableForRequestedQtyInterface $areProductsSalableForRequestedQty,
        private readonly IsProductSalableForRequestedQtyRequestInterfaceFactory $requestFactory
    ) {
    }

    /**
     * Assert every SKU with net demand is still salable for that quantity before appending.
     *
     * @param AppendReservationsInterface $subject
     * @param callable $proceed
     * @param ReservationInterface[] $reservations
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(AppendReservationsInterface $subject, callable $proceed, array $reservations)
    {
        $demandByStock = [];
        foreach ($reservations as $reservation) {
            $quantity = $reservation->getQuantity();
            if ($quantity >= 0) {
                continue;
            }
            $stockId = $reservation->getStockId();
            $sku = $reservation->getSku();
            $demandByStock[$stockId][$sku] = ($demandByStock[$stockId][$sku] ?? 0.0) - $quantity;
        }

        foreach ($demandByStock as $stockId => $demandBySku) {
            $this->assertSalable((int)$stockId, $demandBySku);
        }

        return $proceed($reservations);
    }

    /**
     * Throw when any SKU is not salable for its requested demand on the stock.
     *
     * @param int $stockId
     * @param array<string,float> $demandBySku
     * @return void
     * @throws CouldNotSaveException
     */
    private function assertSalable(int $stockId, array $demandBySku): void
    {
        $requests = [];
        foreach ($demandBySku as $sku => $qty) {
            $requests[] = $this->requestFactory->create(['sku' => (string)$sku, 'qty' => $qty]);
        }

        foreach ($this->areProductsSalableForRequestedQty->execute($requests, $stockId) as $result) {
            if (!$result->isSalable()) {
                throw new CouldNotSaveException(
                    __(
                        'Not enough salable quantity to reserve "%sku" in stock %stock.',
                        ['sku' => $result->getSku(), 'stock' => $stockId]
                    )
                );
            }
        }
    }
}
