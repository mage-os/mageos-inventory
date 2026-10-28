<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after a partial inventory transfer between sources.
 * Both the origin and destination positions are checked. Never blocks the
 * transfer.
 */
class DetectOversellOnTransferInventoryPartiallyPlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check the origin and destination positions for the transferred SKU.
     *
     * @param TransferInventoryPartially $subject
     * @param void $result
     * @param PartialInventoryTransferItemInterface $transfer
     * @param string $originSourceCode
     * @param string $destinationSourceCode
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        TransferInventoryPartially $subject,
        $result,
        PartialInventoryTransferItemInterface $transfer,
        string $originSourceCode,
        string $destinationSourceCode
    ) {
        $sku = $transfer->getSku();
        $this->detectSourceItemsOversell->execute([
            ['source_code' => $originSourceCode, 'sku' => $sku],
            ['source_code' => $destinationSourceCode, 'sku' => $sku],
        ]);

        return $result;
    }
}
