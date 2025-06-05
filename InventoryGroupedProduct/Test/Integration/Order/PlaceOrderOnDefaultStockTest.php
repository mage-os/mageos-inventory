<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryGroupedProduct\Test\Integration\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventorySales\Test\Integration\Order\PlaceOrderOnDefaultStockTest as PlaceOrderTest;

/**
 * Place Order On Default Stock For Grouped Product Test
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PlaceOrderOnDefaultStockTest extends PlaceOrderTest
{
    /**
     * @magentoDataFixture Magento_InventoryGroupedProduct::Test/_files/default_stock_grouped_products.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @see https://app.hiptest.com/projects/69435/test-plan/folders/419537/scenarios/1620162
     */
    public function testPlaceOrderWithInStockProduct(): void
    {
        $groupedSku = 'grouped_in_stock';
        $simpleSku = 'simple_11';
        $quoteItemQty = 1;
        $cart = $this->getCart();

        $groupedProduct = $this->productRepository->get($groupedSku);
        $cartItem = $this->getCartItem($groupedProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $simpleProduct = $this->productRepository->get($simpleSku);
        $cartItem = $this->getCartItem($simpleProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $this->cartRepository->save($cart);

        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNotNull($orderId);

        //cleanup
        $this->deleteOrderById((int)$orderId);
    }

    /**
     * Test to place order and check the status of grouped products as out of stock product
     *
     * @magentoDataFixture Magento_InventoryGroupedProduct::Test/_files/default_stock_grouped_products.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testPlaceOrderToMakeGroupedProductOutOfStock(): void
    {
        $groupedSku = 'grouped_in_stock';
        $simpleSku = 'simple_11';
        $quoteItemQty = 100;
        $cart = $this->getCart();

        $groupedProduct = $this->productRepository->get($groupedSku);
        $cartItem = $this->getCartItem($groupedProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $simpleProduct = $this->productRepository->get($simpleSku);
        $cartItem = $this->getCartItem($simpleProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $this->cartRepository->save($cart);

        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNotNull($orderId);

        $this->orderRepository->get($orderId);

        $productsStockStatus = $this->stockRegistry->getProductStockStatusBySku(
            $simpleSku,
            $this->defaultStockProvider->getId()
        );
        self::assertEquals(SourceItemInterface::STATUS_OUT_OF_STOCK, $productsStockStatus);
        //cleanup
        $this->deleteOrderById((int)$orderId);
    }

    /**
     * @magentoDataFixture Magento_InventoryGroupedProduct::Test/_files/default_stock_grouped_products.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testPlaceOrderWithOutOffStockProduct(): void
    {
        $groupedSku = 'grouped_out_of_stock';
        $simpleSku = 'simple_11';
        $quoteItemQty = 200;
        $cart = $this->getCart();

        $groupedProduct = $this->productRepository->get($groupedSku);
        $cartItem = $this->getCartItem($groupedProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $simpleProduct = $this->productRepository->get($simpleSku);
        $cartItem = $this->getCartItem($simpleProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $this->cartRepository->save($cart);

        self::expectException(LocalizedException::class);
        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNull($orderId);
    }

    /**
     * @magentoDataFixture Magento_InventoryGroupedProduct::Test/_files/default_stock_grouped_products.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoConfigFixture current_store cataloginventory/item_options/backorders 1
     */
    public function testPlaceOrderWithOutOffStockProductAndBackOrdersTurnedOn(): void
    {
        $groupedSku = 'grouped_out_of_stock';
        $simpleSku = 'simple_11';
        $quoteItemQty = 200;
        $cart = $this->getCart();

        $groupedProduct = $this->productRepository->get($groupedSku);
        $cartItem = $this->getCartItem($groupedProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $simpleProduct = $this->productRepository->get($simpleSku);
        $cartItem = $this->getCartItem($simpleProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $this->cartRepository->save($cart);

        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNotNull($orderId);

        //cleanup
        $this->deleteOrderById((int)$orderId);
    }

    /**
     * @magentoDataFixture Magento_InventoryGroupedProduct::Test/_files/default_stock_grouped_products.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/quote.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoConfigFixture current_store cataloginventory/item_options/manage_stock 0
     */
    public function testPlaceOrderWithOutOffStockProductAndManageStockTurnedOff(): void
    {
        $groupedSku = 'grouped_out_of_stock';
        $simpleSku = 'simple_11';
        $quoteItemQty = 200;
        $cart = $this->getCart();

        $groupedProduct = $this->productRepository->get($groupedSku);
        $cartItem = $this->getCartItem($groupedProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $simpleProduct = $this->productRepository->get($simpleSku);
        $cartItem = $this->getCartItem($simpleProduct, $quoteItemQty, (int)$cart->getId());
        $cart->addItem($cartItem);

        $this->cartRepository->save($cart);

        $orderId = $this->cartManagement->placeOrder($cart->getId());

        self::assertNotNull($orderId);

        //cleanup
        $this->deleteOrderById((int)$orderId);
    }
}
