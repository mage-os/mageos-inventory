<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after a bulk source unassignment removes source
 * items: the unassigned source then holds no physical stock while its
 * reservations may still be outstanding. Never blocks the unassignment.
 */
class DetectOversellOnBulkSourceUnassignPlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check every unassigned (source, sku) position.
     *
     * @param BulkSourceUnassign $subject
     * @param int $result
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(BulkSourceUnassign $subject, int $result, array $skus, array $sourceCodes): int
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
