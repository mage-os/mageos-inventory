<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after a bulk inventory transfer moves stock between
 * sources. Both the origin and destination positions are checked. Never blocks
 * the transfer.
 */
class DetectOversellOnBulkInventoryTransferPlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check the origin and destination positions for the transferred SKUs.
     *
     * @param BulkInventoryTransfer $subject
     * @param void $result
     * @param string[] $skus
     * @param string $originSource
     * @param string $destinationSource
     * @param bool $unassignFromOrigin
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        BulkInventoryTransfer $subject,
        $result,
        array $skus,
        string $originSource,
        string $destinationSource,
        bool $unassignFromOrigin
    ) {
        $pairs = [];
        foreach ($skus as $sku) {
            $pairs[] = ['source_code' => $originSource, 'sku' => $sku];
            $pairs[] = ['source_code' => $destinationSource, 'sku' => $sku];
        }
        $this->detectSourceItemsOversell->execute($pairs);

        return $result;
    }
}
