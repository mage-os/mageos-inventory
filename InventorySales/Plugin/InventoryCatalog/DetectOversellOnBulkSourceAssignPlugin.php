<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceAssign;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after a bulk source assignment adds source items
 * for the given SKUs. Never blocks the assignment.
 */
class DetectOversellOnBulkSourceAssignPlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check every assigned (source, sku) position.
     *
     * @param BulkSourceAssign $subject
     * @param int $result
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(BulkSourceAssign $subject, int $result, array $skus, array $sourceCodes): int
    {
        $pairs = [];
        foreach ($sourceCodes as $sourceCode) {
            foreach ($skus as $sku) {
                $pairs[] = ['source_code' => $sourceCode, 'sku' => $sku];
            }
        }
        $this->detectSourceItemsOversell->execute($pairs);

        return $result;
    }
}
