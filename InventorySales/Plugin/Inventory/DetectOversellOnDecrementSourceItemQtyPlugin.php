<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\Inventory;

use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after the order-fulfillment source deduction.
 * Runs on the shipment hot path, so the underlying detection read is targeted to
 * the decremented (source, sku) pairs only. Never blocks the deduction.
 */
class DetectOversellOnDecrementSourceItemQtyPlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check the decremented source items for an oversold position.
     *
     * @param DecrementSourceItemQty $subject
     * @param void $result
     * @param array $sourceItemDecrementData
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(DecrementSourceItemQty $subject, $result, array $sourceItemDecrementData)
    {
        $pairs = [];
        foreach ($sourceItemDecrementData as $data) {
            $sourceItem = $data['source_item'] ?? null;
            if ($sourceItem instanceof SourceItemInterface) {
                $pairs[] = ['source_code' => $sourceItem->getSourceCode(), 'sku' => $sourceItem->getSku()];
            }
        }
        $this->detectSourceItemsOversell->execute($pairs);

        return $result;
    }
}
