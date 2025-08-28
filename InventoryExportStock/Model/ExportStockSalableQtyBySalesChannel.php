<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryExportStockApi\Api\Data\ExportStockSalableQtySearchResultInterface;
use Magento\InventoryExportStockApi\Api\Data\ExportStockSalableQtySearchResultInterfaceFactory;
use Magento\InventoryExportStockApi\Api\ExportStockSalableQtyBySalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;

/**
 * Class ExportStockSalableQty provides product stock information by search criteria
 */
class ExportStockSalableQtyBySalesChannel implements ExportStockSalableQtyBySalesChannelInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ExportStockSalableQtySearchResultInterfaceFactory
     */
    private $exportStockSalableQtySearchResultFactory;

    /**
     * @var PreciseExportStockProcessor
     */
    private $preciseExportStockProcessor;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @var SalesChannelInterfaceFactory
     */
    private $salesChannelInterfaceFactory;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ExportStockSalableQtySearchResultInterfaceFactory $exportStockSalableQtySearchResultFactory
     * @param PreciseExportStockProcessor $preciseExportStockProcessor
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     * @param SalesChannelInterfaceFactory $salesChannelInterfaceFactory
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ExportStockSalableQtySearchResultInterfaceFactory $exportStockSalableQtySearchResultFactory,
        PreciseExportStockProcessor $preciseExportStockProcessor,
        GetStockBySalesChannelInterface $getStockBySalesChannel,
        SalesChannelInterfaceFactory $salesChannelInterfaceFactory
    ) {
        $this->productRepository = $productRepository;
        $this->exportStockSalableQtySearchResultFactory = $exportStockSalableQtySearchResultFactory;
        $this->preciseExportStockProcessor = $preciseExportStockProcessor;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
        $this->salesChannelInterfaceFactory = $salesChannelInterfaceFactory;
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute(
        \Magento\InventorySalesApi\Api\Data\SalesChannelInterface $salesChannel,
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): ExportStockSalableQtySearchResultInterface {
        $stock = $this->getStockBySalesChannel->execute($salesChannel);
        $productSearchResult = $this->getProducts($searchCriteria);
        $items = $this->preciseExportStockProcessor->execute($productSearchResult->getItems(), $stock->getStockId());
        /** @var ExportStockSalableQtySearchResultInterface $searchResult */
        $searchResult = $this->exportStockSalableQtySearchResultFactory->create();
        $searchResult->setSearchCriteria($productSearchResult->getSearchCriteria());
        $searchResult->setItems($items);
        $searchResult->setTotalCount($this->getTotalCountWithStockFilter($searchCriteria, $stock->getStockId()));

        return $searchResult;
    }

    /**
     * Provides product search result by search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SearchResultsInterface
     */
    private function getProducts(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        return $this->productRepository->getList($searchCriteria);
    }

    /**
     * Remove pagination, get all matching products, process through stock filter to
     * get total count of products that have stock assignments (inventory data)
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param int $stockId
     * @return int
     * @throws LocalizedException
     */
    private function getTotalCountWithStockFilter(SearchCriteriaInterface $searchCriteria, int $stockId): int
    {
        // Clone search criteria and remove pagination to get all products
        $searchCriteriaWithoutPagination = clone $searchCriteria;
        $searchCriteriaWithoutPagination->setPageSize(null);
        $searchCriteriaWithoutPagination->setCurrentPage(null);

        // Get all products matching the search criteria (without pagination)
        $allProductsResult = $this->productRepository->getList($searchCriteriaWithoutPagination);

        // Process all products through the stock processor to get only those with inventory
        $allStockItems = $this->preciseExportStockProcessor->execute(
            $allProductsResult->getItems(),
            $stockId
        );

        // Return the count of products that actually have stock assignments
        return count($allStockItems);
    }
}
