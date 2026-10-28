<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterfaceFactory;

/**
 * Build the per-source availability rows for a SKU in a stock.
 *
 * Each enabled source's available quantity nets the physical source quantity against that
 * source's reservation balance, degrading to the physical quantity when source reservations
 * are off.
 */
class SourceViewBuilder
{
    /**
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     * @param GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity
     * @param GetReservationsQuantityBySkusAndSources $getSourceReservations
     * @param SourceViewInterfaceFactory $sourceViewFactory
     */
    public function __construct(
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity,
        private readonly GetReservationsQuantityBySkusAndSources $getSourceReservations,
        private readonly SourceViewInterfaceFactory $sourceViewFactory
    ) {
    }

    /**
     * Per-source availability rows for the stock (all enabled sources).
     *
     * @param string $sku
     * @param int $stockId
     * @param bool $slrEnabled
     * @return SourceViewInterface[]
     */
    public function build(string $sku, int $stockId, bool $slrEnabled): array
    {
        $enabledSources = [];
        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($source->isEnabled()) {
                $enabledSources[(string) $source->getSourceCode()] = $source;
            }
        }
        if (!$enabledSources) {
            return [];
        }

        $sourceCodes = array_keys($enabledSources);
        $physical = $this->getSourceItemQuantity->execute([$sku], $sourceCodes);
        $reservations = $slrEnabled ? $this->getSourceReservations->execute([$sku], $sourceCodes) : [];

        $rows = [];
        foreach ($enabledSources as $sourceCode => $source) {
            $available = ($physical[$sourceCode][$sku] ?? 0.0) + ($reservations[$sourceCode][$sku] ?? 0.0);
            $rows[] = $this->sourceViewFactory->create([
                'sourceCode' => (string) $sourceCode,
                'qty' => max(0.0, $available),
                'name' => $source->getName() ?: (string) $sourceCode,
            ]);
        }

        return $rows;
    }
}
