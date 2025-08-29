<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model\Api\SearchCriteria\FilterProcessor;

use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResource;
use Magento\Inventory\Model\ResourceModel\StockSourceLink as StockSourceLinkResource;

/**
 * Applies inventory stock filtering by joining MSI tables and restricting by stock_id.
 */
class InventoryStockFilter implements CustomFilterInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

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


