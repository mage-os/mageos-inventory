<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryQuoteGraphQl\Model\Cart\MergeCarts;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\QuoteGraphQl\Model\Cart\MergeCarts\CartQuantityValidatorInterface;
use Magento\Checkout\Model\Config;

class CartQuantityValidator implements CartQuantityValidatorInterface
{
    /**
     * @var array
     */
    private $cumulativeQty = [];

    /**
     * CartQuantityValidator Constructor
     *
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly CartItemRepositoryInterface   $cartItemRepository,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly GetStockIdForCurrentWebsite   $getStockIdForCurrentWebsite,
        private readonly Config                        $config,
        private readonly ProductRepositoryInterface    $productRepository
    ) {
    }

    /**
     * Validate combined cart quantities to make sure they are within available stock
     *
     * @param CartInterface $customerCart
     * @param CartInterface $guestCart
     * @return bool
     * @throws InputException
     * @throws LocalizedException
     */
    public function validateFinalCartQuantities(CartInterface $customerCart, CartInterface $guestCart): bool
    {
        $modified = false;
        $stockId = $this->getStockIdForCurrentWebsite->execute();
        $this->cumulativeQty = [];

        /** @var CartItemInterface $guestCartItem */
        foreach ($guestCart->getAllVisibleItems() as $guestCartItem) {
            foreach ($customerCart->getAllItems() as $customerCartItem) {
                if ($customerCartItem->compare($guestCartItem)) {
                    // Delete guest cart item if customer cart has priority
                    if ($this->config->getCartMergePreference() === Config::CART_PREFERENCE_CUSTOMER) {
                        $this->safeDeleteCartItem((int)$guestCart->getId(), (int)$guestCartItem->getItemId());
                        $modified = true;
                        continue;
                    }
                    $sku = $customerCartItem->getProduct()->getSku();
                    if ($customerCartItem->getProduct()->getOptions()) {
                        $product = $this->getProduct((int)$customerCartItem->getProduct()->getId());
                        $sku = $product->getSku();
                    }
                    $stockCurrentQty = $customerCartItem->getChildren()
                        ? $this->validateCompositeProductQty($stockId, $guestCartItem, $customerCartItem)
                        : $this->validateProductQty(
                            $stockId,
                            $sku,
                            $guestCartItem->getQty(),
                            $customerCartItem->getQty()
                        );

                    // Delete customer cart item if guest cart has priority
                    if ($this->config->getCartMergePreference() === Config::CART_PREFERENCE_GUEST) {
                        $this->safeDeleteCartItem((int)$customerCart->getId(), (int)$customerCartItem->getItemId());
                        $modified = true;
                    }

                    // If cart merge has priority or after guest priority, validate stock quantity
                    if (!$stockCurrentQty) {
                        $this->safeDeleteCartItem((int)$guestCart->getId(), (int)$guestCartItem->getItemId());
                        $modified = true;
                    }
                }
            }
        }
        $this->cumulativeQty = [];

        return $modified;
    }

    /**
     * Validate product stock availability
     *
     * @param int $stockId
     * @param string $sku
     * @param float $guestItemQty
     * @param float $customerItemQty
     * @return bool
     * @throws InputException
     * @throws LocalizedException
     */
    private function validateProductQty(int $stockId, string $sku, float $guestItemQty, float $customerItemQty): bool
    {
        $salableQty = $this->getProductSalableQty->execute($sku, $stockId);
        $this->cumulativeQty[$sku] ??= 0;
        // Validate cart item quantity as per selected cart merge preference store config
        $this->cumulativeQty[$sku] += $this->getCurrentCartItemQty($guestItemQty, $customerItemQty);

        return $salableQty >= $this->cumulativeQty[$sku];
    }

    /**
     * Validate composite product stock availability
     *
     * @param int $stockId
     * @param Item $guestCartItem
     * @param Item $customerCartItem
     * @return bool
     * @throws InputException
     * @throws LocalizedException
     */
    private function validateCompositeProductQty(int $stockId, Item $guestCartItem, Item $customerCartItem): bool
    {
        $guestChildItems = $this->retrieveChildItems($guestCartItem);
        foreach ($customerCartItem->getChildren() as $customerChildItem) {
            $sku = $customerChildItem->getProduct()->getSku();
            if ($customerChildItem->getProduct()->getOptions()) {
                $product = $this->getProduct((int)$customerChildItem->getProduct()->getId());
                $sku = $product->getSku();
            }
            $customerItemQty = $customerCartItem->getQty() * $customerChildItem->getQty();
            $guestItemQty = $guestCartItem->getQty() * $guestChildItems[$sku]->getQty();
            if (!$this->validateProductQty($stockId, $sku, $guestItemQty, $customerItemQty)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieve product by ID
     *
     * @param int $productId
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    private function getProduct(int $productId): ProductInterface
    {
        return $this->productRepository->getById($productId);
    }

    /**
     * Retrieve child quote items mapped by sku
     *
     * @param Item $quoteItem
     * @return array
     */
    private function retrieveChildItems(Item $quoteItem): array
    {
        $childItems = [];
        foreach ($quoteItem->getChildren() as $childItem) {
            $childItems[$childItem->getProduct()->getSku()] = $childItem;
        }

        return $childItems;
    }

    /**
     * Get current cart item qty for guest cart merge
     *
     * @param float $guestCartItemQty
     * @param float $customerCartItemQty
     * @return float
     */
    private function getCurrentCartItemQty(float $guestCartItemQty, float $customerCartItemQty): float
    {
        return match ($this->config->getCartMergePreference()) {
            Config::CART_PREFERENCE_CUSTOMER => $customerCartItemQty,
            Config::CART_PREFERENCE_GUEST => $guestCartItemQty,
            default => $guestCartItemQty + $customerCartItemQty
        };
    }

    /**
     * Safely delete item from cart using cart id and cart item id
     *
     * @param int $cartId
     * @param int $itemId
     * @return void
     */
    private function safeDeleteCartItem(int $cartId, int $itemId): void
    {
        try {
            $this->cartItemRepository->deleteById($cartId, $itemId);
        } catch (NoSuchEntityException | CouldNotSaveException $e) { // phpcs:ignore
            // Optionally log the error here
        }
    }
}
