<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\IsStockItemSalableCondition;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;

/**
 * Condition for backorders configuration.
 */
class BackordersCondition implements GetIsStockItemSalableConditionInterface
{
    /**
     * @var StockConfigurationInterface
     */
    private $configuration;

    /**
     * @param StockConfigurationInterface $configuration
     */
    public function __construct(StockConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Select $select): string
    {
        $globalBackorders = (int)$this->configuration->getBackorders();
        $itemBackordersCondition = 'legacy_stock_item.backorders <> ' . StockItemConfigurationInterface::BACKORDERS_NO;
        $useDefaultBackorders = 'legacy_stock_item.use_config_backorders';
        $itemMinQty = 'legacy_stock_item.min_qty';
        $globalMinQty = (float) $this->configuration->getMinQty();
        $minQty =  (string) $select->getConnection()->getCheckSql(
            'legacy_stock_item.use_config_min_qty = 1',
            $globalMinQty,
            $itemMinQty
        );

        $isBackorderEnabled = $globalBackorders === StockItemConfigurationInterface::BACKORDERS_NO
            ? $useDefaultBackorders . ' = ' . StockItemConfigurationInterface::BACKORDERS_NO . ' AND ' .
            $itemBackordersCondition
            : $useDefaultBackorders . ' = ' . StockItemConfigurationInterface::BACKORDERS_YES_NONOTIFY .
            ' OR ' . $itemBackordersCondition;

        $isAnyStockItemInStock = (string) $select->getConnection()
            ->getCheckSql(
                'source_item.' . SourceItemInterface::STATUS . ' = ' . SourceItemInterface::STATUS_OUT_OF_STOCK,
                0,
                1
            );

        return "($isBackorderEnabled) AND ($minQty >= 0) AND SUM($isAnyStockItemInStock)";
    }
}
