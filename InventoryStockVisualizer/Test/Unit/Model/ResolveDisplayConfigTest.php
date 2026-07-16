<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Product\StockVisualizerAttributes as Attr;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ResolveDisplayConfig
 */
class ResolveDisplayConfigTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var ResolveDisplayConfig
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->config->method('getDisplayType')->willReturn(Config::DISPLAY_TYPE_LEVEL);
        $this->config->method('getLevelBasis')->willReturn(Config::LEVEL_BASIS_QUANTITY);
        $this->config->method('getLevelHigh')->willReturn(10.0);
        $this->config->method('getLevelLow')->willReturn(3.0);
        $this->model = new ResolveDisplayConfig(
            $this->config,
            $this->createMock(ProductRepositoryInterface::class)
        );
    }

    /**
     * Without a product the store defaults are used.
     *
     * @return void
     */
    public function testDefaultsWhenNoProduct(): void
    {
        $displayConfig = $this->model->forProduct(null);

        $this->assertSame(Config::DISPLAY_TYPE_LEVEL, $displayConfig->getDisplayType());
        $this->assertSame(10.0, $displayConfig->getLevelHigh());
        $this->assertSame(3.0, $displayConfig->getLevelLow());
        $this->assertNull($displayConfig->getFullQty());
    }

    /**
     * A per-product value overrides the store default; blanks fall through.
     *
     * @return void
     */
    public function testProductOverrideWins(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnMap([
            [Attr::DISPLAY_TYPE, Config::DISPLAY_TYPE_QUANTITY],
            [Attr::LEVEL_BASIS, ''],
            [Attr::LEVEL_HIGH, '5'],
            [Attr::LEVEL_LOW, null],
            [Attr::FULL_QTY, '40'],
        ]);

        $displayConfig = $this->model->forProduct($product);

        $this->assertSame(Config::DISPLAY_TYPE_QUANTITY, $displayConfig->getDisplayType());
        $this->assertSame(Config::LEVEL_BASIS_QUANTITY, $displayConfig->getLevelBasis());
        $this->assertSame(5.0, $displayConfig->getLevelHigh());
        $this->assertSame(3.0, $displayConfig->getLevelLow());
        $this->assertSame(40.0, $displayConfig->getFullQty());
    }
}
