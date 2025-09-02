<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model\Api\SearchCriteria\FilterProcessor;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\Data\Collection\AbstractDb;

/**
 * Applies inventory stock filtering by joining MSI tables and restricting by stock_id.
 */
class InventoryStockFilter implements CustomFilterInterface
{
    /**
     * @inheritDoc
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        $stockId = (int)$filter->getValue();

        $select = $collection->getSelect();
        $stockLinkAlias = 'inventory_stock_link';

        // Apply the dynamic stock id constraint (joins are provided by JoinProcessor)
        $select->where($stockLinkAlias . '.stock_id = ?', $stockId);

        // Avoid duplicates when a product has multiple source items in the same stock
        $select->group('e.entity_id');

        return true;
    }
}


