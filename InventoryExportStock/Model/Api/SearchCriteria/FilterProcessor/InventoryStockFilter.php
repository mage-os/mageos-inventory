<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model\Api\SearchCriteria\FilterProcessor;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\FilterProcessor\CustomFilterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Applies inventory stock filtering by joining MSI tables and restricting by stock_id.
 */
class InventoryStockFilter implements CustomFilterInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(Filter $filter, AbstractDb $collection): bool
    {
        $stockId = (int)$filter->getValue();
        $select = $collection->getSelect();

        if (Stock::DEFAULT_STOCK_ID === $stockId) {
            $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
            $select->join(
                ['ci_si' => $this->resourceConnection->getTableName('cataloginventory_stock_item')],
                'ci_si.product_id = e.' . $linkField,
                []
            );
        } else {
            $select->join(
                ['inventory_source_item' => $this->resourceConnection->getTableName('inventory_source_item')],
                'inventory_source_item.sku = e.sku',
                []
            )->join(
                ['stock_source_link' => $this->resourceConnection->getTableName('inventory_source_stock_link')],
                'stock_source_link.source_code = inventory_source_item.source_code',
                []
            )->where(
                'stock_source_link.stock_id = ?',
                $stockId
            );
        }

        return true;
    }
}
