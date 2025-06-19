<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryApi\StockRepository;

use Magento\Framework\Exception\StateException;
use Magento\InventoryApi\Api\Data\StockSearchResultsInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventorySalesApi\Model\GetAssignedSalesChannelsForStockInterface;
use Psr\Log\LoggerInterface;

/**
 * Load Sales Channels for Stock on GetList method of StockRepositoryInterface
 */
class LoadSalesChannelsOnGetListPlugin
{
    /**
     * @var GetAssignedSalesChannelsForStockInterface
     */
    private $getAssignedSalesChannelsForStock;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param GetAssignedSalesChannelsForStockInterface $getAssignedSalesChannelsForStock
     * @param LoggerInterface $logger
     */
    public function __construct(
        GetAssignedSalesChannelsForStockInterface $getAssignedSalesChannelsForStock,
        LoggerInterface $logger
    ) {
        $this->getAssignedSalesChannelsForStock = $getAssignedSalesChannelsForStock;
        $this->logger = $logger;
    }

    /**
     * Enrich the given Stock Objects with the assigned sales channel entities
     *
     * @param StockRepositoryInterface $subject
     * @param StockSearchResultsInterface $stockSearchResults
     * @return StockSearchResultsInterface
     * @throws StateException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        StockRepositoryInterface $subject,
        StockSearchResultsInterface $stockSearchResults
    ): StockSearchResultsInterface {
        try {
            return $this->doAfterGetList($stockSearchResults);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new StateException(__('Could not load Sales Channels for Stock'), $e);
        }
    }

    /**
     * Enriches stock items with assigned sales channels by setting sales channel data in their extension attributes.
     *
     * @param StockSearchResultsInterface $stockSearchResults
     * @return StockSearchResultsInterface
     */
    private function doAfterGetList(StockSearchResultsInterface $stockSearchResults): StockSearchResultsInterface
    {
        $stocks = [];
        foreach ($stockSearchResults->getItems() as $stock) {
            $salesChannels = $this->getAssignedSalesChannelsForStock->execute((int)$stock->getStockId());

            $extensionAttributes = $stock->getExtensionAttributes();
            $extensionAttributes->setSalesChannels($salesChannels);
            $stock->setExtensionAttributes($extensionAttributes);
            $stocks[] = $stock;
        }
        $stockSearchResults->setItems($stocks);
        return $stockSearchResults;
    }
}
