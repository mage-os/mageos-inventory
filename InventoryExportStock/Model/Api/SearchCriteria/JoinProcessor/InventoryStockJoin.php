<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model\Api\SearchCriteria\JoinProcessor;

use Magento\Framework\Api\SearchCriteria\CollectionProcessor\JoinProcessor\CustomJoinInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResource;
use Magento\Inventory\Model\ResourceModel\StockSourceLink as StockSourceLinkResource;

/**
 * Adds necessary MSI joins to product collection to enable stock filtering.
 */
class InventoryStockJoin implements CustomJoinInterface
{
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function apply(AbstractDb $collection)
    {
        $select = $collection->getSelect();
        $from = $select->getPart(Select::FROM);

        $stockLinkAlias = 'inventory_stock_link';
        $sourceItemAlias = 'inventory_source_item';

        if (!isset($from[$stockLinkAlias])) {
            $select->joinInner(
                [$stockLinkAlias => $this->resourceConnection->getTableName(StockSourceLinkResource::TABLE_NAME_STOCK_SOURCE_LINK)],
                // no stock_id constraint here; condition is applied by the filter processor
                '1=1',
                []
            );
        }

        // Join source items by source_code, relate SKU to product
        if (!isset($from[$sourceItemAlias])) {
            $select->joinInner(
                [$sourceItemAlias => $this->resourceConnection->getTableName(SourceItemResource::TABLE_NAME_SOURCE_ITEM)],
                $sourceItemAlias . '.source_code = ' . $stockLinkAlias . '.source_code AND ' .
                $sourceItemAlias . '.sku = e.sku',
                []
            );
        }

        return true;
    }
}


