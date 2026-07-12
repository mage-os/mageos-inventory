<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySales\Model\SalesEventToArrayConverter;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;

/**
 * Source-aware implementation of reservation placement.
 */
class PlaceSourceAwareReservationsForSalesEvent implements PlaceReservationsForSalesEventInterface
{
    /**
     * @param ReservationBuilderInterface $reservationBuilder
     * @param AppendReservationsInterface $appendReservations
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed
     * @param SerializerInterface $serializer
     * @param SalesEventToArrayConverter $salesEventToArrayConverter
     * @param AllocateItemsToSources $allocateItemsToSources
     * @param DistributeCompensationToSources $distributeCompensationToSources
     */
    public function __construct(
        private readonly ReservationBuilderInterface $reservationBuilder,
        private readonly AppendReservationsInterface $appendReservations,
        private readonly GetStockBySalesChannelInterface $getStockBySalesChannel,
        private readonly GetProductTypesBySkusInterface $getProductTypesBySkus,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed,
        private readonly SerializerInterface $serializer,
        private readonly SalesEventToArrayConverter $salesEventToArrayConverter,
        private readonly AllocateItemsToSources $allocateItemsToSources,
        private readonly DistributeCompensationToSources $distributeCompensationToSources
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $items, SalesChannelInterface $salesChannel, SalesEventInterface $salesEvent): void
    {
        if (empty($items)) {
            return;
        }

        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        $qtysBySku = $this->getQuantitiesBySku($items);

        $eventData = $this->salesEventToArrayConverter->execute($salesEvent);
        $metadata = $this->serializer->serialize($eventData);
        $objectIncrementId = ($eventData['object_increment_id'] ?? '') !== ''
            ? (string)$eventData['object_increment_id']
            : null;

        $reservations = [];
        foreach ($this->getAllocationsBySku($qtysBySku, $stockId, $objectIncrementId) as $sku => $allocations) {
            foreach ($allocations as $allocation) {
                $reservations[] = $this->reservationBuilder
                    ->setSku((string)$sku)
                    ->setQuantity((float)$allocation['quantity'])
                    ->setStockId($stockId)
                    ->setMetadata($metadata)
                    ->setSourceCode($allocation['source_code'])
                    ->setObjectIncrementId($objectIncrementId)
                    ->build();
            }
        }
        $this->appendReservations->execute($reservations);
    }

    /**
     * Aggregate the event items into a signed quantity per SKU, applying the legacy product type filter.
     *
     * @param ItemToSellInterface[] $items
     * @return array<string,float>
     */
    private function getQuantitiesBySku(array $items): array
    {
        $skus = [];
        foreach ($items as $item) {
            $skus[] = $item->getSku();
        }
        $productTypes = $this->getProductTypesBySkus->execute($skus);

        $qtysBySku = [];
        foreach ($items as $item) {
            $sku = $item->getSku();
            $skuNotExistInCatalog = !isset($productTypes[$sku]);
            if ($skuNotExistInCatalog
                || $this->isSourceItemManagementAllowed->execute($productTypes[$sku])
            ) {
                $qtysBySku[$sku] = ($qtysBySku[$sku] ?? 0.0) + (float)$item->getQuantity();
            }
        }

        return $qtysBySku;
    }

    /**
     * Resolve the per-source allocation of every SKU, keeping the sign of the aggregated quantity.
     *
     * @param array<string,float> $qtysBySku
     * @param int $stockId
     * @param string|null $objectIncrementId
     * @return array<string,array<int,array{source_code:string|null,quantity:float}>>
     */
    private function getAllocationsBySku(array $qtysBySku, int $stockId, ?string $objectIncrementId): array
    {
        if ($objectIncrementId === null) {
            return $this->getStockScopedAllocations($qtysBySku);
        }

        [$demand, $release, $allocations] = $this->partitionBySign($qtysBySku);

        $allocations = $this->mergeAllocations(
            $allocations,
            $this->allocateItemsToSources->execute($demand, $stockId),
            -1.0
        );

        return $this->mergeAllocations(
            $allocations,
            $this->distributeCompensationToSources->execute($release, $stockId, $objectIncrementId),
            1.0
        );
    }

    /**
     * Build one stock-scoped (NULL source) allocation per SKU.
     *
     * @param array<string,float> $qtysBySku
     * @return array<string,array<int,array{source_code:string|null,quantity:float}>>
     */
    private function getStockScopedAllocations(array $qtysBySku): array
    {
        $allocations = [];
        foreach ($qtysBySku as $sku => $qty) {
            $allocations[$sku] = [['source_code' => null, 'quantity' => (float)$qty]];
        }

        return $allocations;
    }

    /**
     * Split the signed quantities into demand, release and zero-quantity allocations.
     *
     * @param array<string,float> $qtysBySku
     * @return array{0: array<string,float>, 1: array<string,float>, 2: array}
     */
    private function partitionBySign(array $qtysBySku): array
    {
        $demand = [];
        $release = [];
        $allocations = [];
        foreach ($qtysBySku as $sku => $qty) {
            if ($qty < 0) {
                $demand[$sku] = -$qty;
            } elseif ($qty > 0) {
                $release[$sku] = $qty;
            } else {
                $allocations[$sku] = [['source_code' => null, 'quantity' => 0.0]];
            }
        }

        return [$demand, $release, $allocations];
    }

    /**
     * Append per-source allocations applying the given sign to their quantities.
     *
     * @param array $allocations
     * @param array<string,array<int,array{source_code:string|null,quantity:float}>> $allocationsBySku
     * @param float $sign
     * @return array
     */
    private function mergeAllocations(array $allocations, array $allocationsBySku, float $sign): array
    {
        foreach ($allocationsBySku as $sku => $skuAllocations) {
            foreach ($skuAllocations as $allocation) {
                $allocations[$sku][] = [
                    'source_code' => $allocation['source_code'],
                    'quantity' => $sign * $allocation['quantity'],
                ];
            }
        }

        return $allocations;
    }
}
