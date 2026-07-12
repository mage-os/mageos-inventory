<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;

/**
 * Allocate the requested quantities across the enabled sources of a stock, in priority order.
 */
class AllocateItemsToSources
{
    private const FLOAT_EPSILON = 0.000001;

    /**
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesByPriority
     * @param GetSourceItemQuantityBySkusAndSources $getSourceItemQuantityBySkusAndSources
     * @param GetReservationsQuantityBySkusAndSources $getReservationsQuantityBySkusAndSources
     */
    public function __construct(
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesByPriority,
        private readonly GetSourceItemQuantityBySkusAndSources $getSourceItemQuantityBySkusAndSources,
        private readonly GetReservationsQuantityBySkusAndSources $getReservationsQuantityBySkusAndSources
    ) {
    }

    /**
     * Allocate the requested (positive) quantities per SKU to the sources of the given stock.
     *
     * @param array<string,float> $qtysBySku requested positive quantity per SKU
     * @param int $stockId
     * @return array<string,array<int,array{source_code:string|null,quantity:float}>>
     */
    public function execute(array $qtysBySku, int $stockId): array
    {
        if (empty($qtysBySku)) {
            return [];
        }

        $sourceCodes = $this->getEnabledSourceCodes($stockId);
        $skus = array_map('strval', array_keys($qtysBySku));

        if (empty($sourceCodes)) {
            $result = [];
            foreach ($qtysBySku as $sku => $qty) {
                $result[(string)$sku] = [['source_code' => null, 'quantity' => (float)$qty]];
            }
            return $result;
        }

        $physicalQtys = $this->getSourceItemQuantityBySkusAndSources->execute($skus, $sourceCodes);
        $reservationQtys = $this->getReservationsQuantityBySkusAndSources->execute($skus, $sourceCodes);

        $lastIndex = count($sourceCodes) - 1;
        $result = [];
        foreach ($qtysBySku as $sku => $qty) {
            $sku = (string)$sku;
            $remaining = (float)$qty;
            $allocations = [];
            foreach ($sourceCodes as $index => $sourceCode) {
                if ($remaining <= self::FLOAT_EPSILON) {
                    break;
                }
                $available = ($physicalQtys[$sourceCode][$sku] ?? 0.0)
                    + ($reservationQtys[$sourceCode][$sku] ?? 0.0);
                $take = $index === $lastIndex ? $remaining : min($remaining, max(0.0, $available));
                if ($take > self::FLOAT_EPSILON) {
                    $allocations[] = ['source_code' => $sourceCode, 'quantity' => $take];
                    $remaining -= $take;
                }
            }
            $result[$sku] = $allocations;
        }

        return $result;
    }

    /**
     * Get the codes of the enabled sources assigned to the stock, ordered by priority.
     *
     * @param int $stockId
     * @return string[]
     */
    private function getEnabledSourceCodes(int $stockId): array
    {
        $sourceCodes = [];
        foreach ($this->getSourcesByPriority->execute($stockId) as $source) {
            if ($source->isEnabled()) {
                $sourceCodes[] = $source->getSourceCode();
            }
        }

        return $sourceCodes;
    }
}
