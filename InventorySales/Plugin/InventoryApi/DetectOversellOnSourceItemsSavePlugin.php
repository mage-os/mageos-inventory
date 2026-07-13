<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;

/**
 * Detect an oversold position after a source item save (admin edit, REST/SOAP,
 * CSV import, mass update, legacy quantity sync). Never blocks the save.
 */
class DetectOversellOnSourceItemsSavePlugin
{
    /**
     * @param DetectSourceItemsOversell $detectSourceItemsOversell
     */
    public function __construct(
        private readonly DetectSourceItemsOversell $detectSourceItemsOversell
    ) {
    }

    /**
     * Check the saved source items for an oversold position.
     *
     * @param SourceItemsSaveInterface $subject
     * @param void $result
     * @param SourceItemInterface[] $sourceItems
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(SourceItemsSaveInterface $subject, $result, array $sourceItems)
    {
        $pairs = [];
        foreach ($sourceItems as $sourceItem) {
            $pairs[] = ['source_code' => $sourceItem->getSourceCode(), 'sku' => $sourceItem->getSku()];
        }
        $this->detectSourceItemsOversell->execute($pairs);

        return $result;
    }
}
