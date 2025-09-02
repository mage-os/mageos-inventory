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
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\Inventory\Model\ResourceModel\StockSourceLink;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;

/**
 * Applies inventory stock filtering by joining MSI tables and restricting by stock_id.
 */
class InventoryStockFilter implements CustomFilterInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        $stockId = (int)$filter->getValue();
        if ($this->defaultStockProvider->getId() === $stockId) {
            return true;
        }

        $select = $collection->getSelect();
        $select->join(
            ['inventory_source_item' => $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM)],
            'inventory_source_item.sku = main_table.sku',
            []
        )->join(
            ['stock_source_link' => $this->resourceConnection->getTableName(StockSourceLink::TABLE_NAME_STOCK_SOURCE_LINK)],
            'stock_source_link.source_code = inventory_source_item.source_code',
            []
        )->where(
            'stock_source_link.stock_id = ?',
            $stockId
        );

        return true;
    }
}
