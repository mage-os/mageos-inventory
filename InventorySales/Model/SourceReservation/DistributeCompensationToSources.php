<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingSourceReservations;

/**
 * Distribute a compensation quantity against the outstanding per-source balance of an order.
 */
class DistributeCompensationToSources
{
    private const FLOAT_EPSILON = 0.000001;

    /**
     * @param GetPendingSourceReservations $getPendingSourceReservations
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesByPriority
     */
    public function __construct(
        private readonly GetPendingSourceReservations $getPendingSourceReservations,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesByPriority
    ) {
    }

    /**
     * Distribute the compensation (positive) quantities per SKU against the order's pending allocation.
     *
     * @param array<string,float> $qtysBySku positive quantity per SKU to release
     * @param int $stockId
     * @param string $objectIncrementId
     * @return array<string,array<int,array{source_code:string|null,quantity:float}>>
     */
    public function execute(array $qtysBySku, int $stockId, string $objectIncrementId): array
    {
        if (empty($qtysBySku)) {
            return [];
        }

        $skus = array_map('strval', array_keys($qtysBySku));
        $pendingBalances = $this->getPendingSourceReservations->execute($objectIncrementId, $skus, $stockId);
        $priorityOrder = array_flip($this->getSourceCodesOrderedByPriority($stockId));

        $result = [];
        foreach ($qtysBySku as $sku => $qty) {
            $sku = (string)$sku;
            $remaining = (float)$qty;
            $allocations = [];
            foreach ($this->getOutstandingBySource($pendingBalances[$sku] ?? [], $priorityOrder) as $balance) {
                if ($remaining <= self::FLOAT_EPSILON) {
                    break;
                }
                $take = min($remaining, $balance['outstanding']);
                if ($take > self::FLOAT_EPSILON) {
                    $allocations[] = ['source_code' => $balance['source_code'], 'quantity' => $take];
                    $remaining -= $take;
                }
            }
            if ($remaining > self::FLOAT_EPSILON) {
                $allocations[] = ['source_code' => null, 'quantity' => $remaining];
            }
            $result[$sku] = $allocations;
        }

        return $result;
    }

    /**
     * Get the sources with a negative balance as positive outstanding quantities, deterministically ordered.
     *
     * @param array<string,float> $balancesBySource [source_code|''] => SUM(quantity)
     * @param array<string,int> $priorityOrder source_code => priority index
     * @return array<int,array{source_code:string,outstanding:float}>
     */
    private function getOutstandingBySource(array $balancesBySource, array $priorityOrder): array
    {
        $outstanding = [];
        foreach ($balancesBySource as $sourceCode => $balance) {
            if ($sourceCode !== '' && $balance < -self::FLOAT_EPSILON) {
                $outstanding[] = ['source_code' => (string)$sourceCode, 'outstanding' => -$balance];
            }
        }

        usort(
            $outstanding,
            static function (array $left, array $right) use ($priorityOrder): int {
                $leftPriority = $priorityOrder[$left['source_code']] ?? PHP_INT_MAX;
                $rightPriority = $priorityOrder[$right['source_code']] ?? PHP_INT_MAX;

                return $leftPriority <=> $rightPriority ?: $left['source_code'] <=> $right['source_code'];
            }
        );

        return $outstanding;
    }

    /**
     * Get the codes of the sources assigned to the stock, ordered by priority.
     *
     * @param int $stockId
     * @return string[]
     */
    private function getSourceCodesOrderedByPriority(int $stockId): array
    {
        $sourceCodes = [];
        foreach ($this->getSourcesByPriority->execute($stockId) as $source) {
            $sourceCodes[] = $source->getSourceCode();
        }

        return $sourceCodes;
    }
}
