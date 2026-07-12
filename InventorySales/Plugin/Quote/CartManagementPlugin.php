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
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Acquire per-source inventory locks during cart place order to prevent overselling.
 */
class CartManagementPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

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
     * @param CartRepositoryInterface $cartRepository
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param AcquireStockItemLocks $acquireStockItemLocks
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param StoreManagerInterface $storeManager
     * @param ReservationExecutionInterface $reservationExecution
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        AcquireStockItemLocks $acquireStockItemLocks,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        StoreManagerInterface $storeManager,
        ReservationExecutionInterface $reservationExecution
    ) {
        $this->cartRepository = $cartRepository;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->acquireStockItemLocks = $acquireStockItemLocks;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->storeManager = $storeManager;
        $this->reservationExecution = $reservationExecution;
    }

    /**
     * Acquire source-level locks around place order for both guest and customer checkout.
     *
     * @param CartManagementInterface $subject
     * @param callable $proceed
     * @param int $cartId
     * @param PaymentInterface|null $paymentMethod
     * @return int Order ID
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundPlaceOrder(
        CartManagementInterface $subject,
        callable $proceed,
        $cartId,
        ?PaymentInterface $paymentMethod = null
    ) {
        if (!$this->reservationExecution->isDeferred()) {
            return $proceed($cartId, $paymentMethod);
        }

        try {
            $quote = $this->cartRepository->getActive($cartId);
        } catch (NoSuchEntityException $exception) {
            // Async order processing can work with an inactive quote after checkout message submission.
            $quote = $this->cartRepository->get($cartId);
        }
        $websiteId = (int)$this->storeManager->getStore($quote->getStoreId())->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();

        $productIds = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $productIds[] = $item->getProductId();
        }
        if (!$productIds) {
            return $proceed($cartId, $paymentMethod);
        }

        $skus = $this->getSkusByProductIds->execute($productIds);

        try {
            $this->acquireStockItemLocks->execute(array_map('strval', $skus), $stockId);

            return $proceed($cartId, $paymentMethod);
        } finally {
            $this->acquireStockItemLocks->releaseAll();
        }
    }
}
