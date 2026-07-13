<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySales\Model\ReservationExecutionInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Acquire per-source inventory locks around order submission to prevent overselling.
 *
 * The lock wraps QuoteManagement::submit rather than placeOrder because submit is
 * the single point every order-placement path funnels through: front-end and API
 * checkout reach it via placeOrder -> placeOrderRun -> $this->submit (intercepted,
 * since $this is the plugin interceptor), and admin order creation calls submit
 * directly. Locking here therefore also covers the admin/API paths that bypass the
 * placeOrder entry point.
 */
class CartManagementPlugin
{
    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var AcquireStockItemLocks
     */
    private $acquireStockItemLocks;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ReservationExecutionInterface
     */
    private $reservationExecution;

    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param AcquireStockItemLocks $acquireStockItemLocks
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param StoreManagerInterface $storeManager
     * @param ReservationExecutionInterface $reservationExecution
     */
    public function __construct(
        GetSkusByProductIdsInterface $getSkusByProductIds,
        AcquireStockItemLocks $acquireStockItemLocks,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StoreManagerInterface $storeManager,
        ReservationExecutionInterface $reservationExecution
    ) {
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->acquireStockItemLocks = $acquireStockItemLocks;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->storeManager = $storeManager;
        $this->reservationExecution = $reservationExecution;
    }

    /**
     * Acquire source-level locks around order submission for every checkout path.
     *
     * @param QuoteManagement $subject
     * @param callable $proceed
     * @param Quote $quote
     * @param array $orderData
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSubmit(
        QuoteManagement $subject,
        callable $proceed,
        Quote $quote,
        $orderData = []
    ) {
        if (!$this->reservationExecution->isDeferred()) {
            return $proceed($quote, $orderData);
        }

        $websiteId = (int)$this->storeManager->getStore($quote->getStoreId())->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();

        $productIds = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $productIds[] = $item->getProductId();
        }
        if (!$productIds) {
            return $proceed($quote, $orderData);
        }

        $skus = $this->getSkusByProductIds->execute($productIds);

        try {
            $this->acquireStockItemLocks->execute(array_map('strval', $skus), $stockId);

            return $proceed($quote, $orderData);
        } finally {
            $this->acquireStockItemLocks->releaseAll();
        }
    }
}
