<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;

/**
 * List the enabled sources (code + name) assigned to a stock, in priority order.
 */
class GetEnabledSources
{
    /**
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     */
    public function __construct(
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
    ) {
    }

    /**
     * @param int $stockId
     * @return array<int, array{code: string, name: string}>
     */
    public function execute(int $stockId): array
    {
        $sources = [];
        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($source->isEnabled()) {
                $code = (string) $source->getSourceCode();
                $sources[] = ['code' => $code, 'name' => $source->getName() ?: $code];
            }
        }

        return $sources;
    }
}
