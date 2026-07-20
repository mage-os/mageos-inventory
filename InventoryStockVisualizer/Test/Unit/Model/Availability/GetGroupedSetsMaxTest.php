<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Availability;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryStockVisualizer\Model\Availability\GetGroupedSetsMax;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see GetGroupedSetsMax
 */
class GetGroupedSetsMaxTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private $productRepository;

    /**
     * @var GetProductSalableQtyInterface|MockObject
     */
    private $getProductSalableQty;

    /**
     * @var GetSkusByProductIdsInterface|MockObject
     */
    private $getSkusByProductIds;

    /**
     * @var Grouped|MockObject
     */
    private $type;

    /**
     * @var GetGroupedSetsMax
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->getProductSalableQty = $this->createMock(GetProductSalableQtyInterface::class);
        $this->getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $this->type = $this->createMock(Grouped::class);
        $this->model = new GetGroupedSetsMax(
            $this->productRepository,
            $this->getProductSalableQty,
            $this->getSkusByProductIds
        );
    }

    /**
     * A non-grouped product yields null.
     *
     * @return void
     */
    public function testNonGroupedReturnsNull(): void
    {
        $this->givenProduct('simple', [], []);

        $this->assertNull($this->model->execute('SKU', self::STOCK_ID));
    }

    /**
     * A grouped product with no children yields null.
     *
     * @return void
     */
    public function testNoChildrenReturnsNull(): void
    {
        $this->givenProduct(Grouped::TYPE_CODE, [], []);

        $this->assertNull($this->model->execute('GRP', self::STOCK_ID));
    }

    /**
     * Max sets is the minimum of floor(childSalable / recipeQty) across components.
     *
     * @return void
     */
    public function testMinAcrossComponents(): void
    {
        $this->givenProduct(
            Grouped::TYPE_CODE,
            [6 => 'A', 7 => 'B'],
            ['A' => 2.0, 'B' => 1.0]
        );
        $this->getProductSalableQty->method('execute')->willReturnMap([
            ['A', self::STOCK_ID, 30.0],
            ['B', self::STOCK_ID, 8.0],
        ]);

        // A: floor(30/2)=15 ; B: floor(8/1)=8 => min 8
        $this->assertSame(8, $this->model->execute('GRP', self::STOCK_ID));
    }

    /**
     * An out-of-stock component (absent from the in-stock link recipe) still zeroes the sets.
     *
     * @return void
     */
    public function testOutOfStockComponentZeroesSets(): void
    {
        // Child C (id 8) is out of stock: it is in the full children list but NOT in the link recipe.
        $this->givenProduct(
            Grouped::TYPE_CODE,
            [6 => 'A', 8 => 'C'],
            ['A' => 1.0]
        );
        $this->getProductSalableQty->method('execute')->willReturnMap([
            ['A', self::STOCK_ID, 20.0],
            ['C', self::STOCK_ID, 0.0],
        ]);

        $this->assertSame(0, $this->model->execute('GRP', self::STOCK_ID));
    }

    /**
     * A zero default quantity falls back to a recipe of one.
     *
     * @return void
     */
    public function testDefaultQtyFallsBackToOne(): void
    {
        $this->givenProduct(
            Grouped::TYPE_CODE,
            [6 => 'A'],
            ['A' => 0.0]
        );
        $this->getProductSalableQty->method('execute')->willReturn(5.0);

        $this->assertSame(5, $this->model->execute('GRP', self::STOCK_ID));
    }

    /**
     * @param string $typeId
     * @param array<int, string> $skusById full child id => sku (the unfiltered children list)
     * @param array<string, float> $recipeBySku in-stock link recipe (sku => default qty)
     * @return void
     */
    private function givenProduct(string $typeId, array $skusById, array $recipeBySku): void
    {
        $links = [];
        foreach ($recipeBySku as $sku => $qty) {
            $links[] = new DataObject([
                'link_type' => 'associated',
                'linked_product_sku' => $sku,
                'extension_attributes' => new DataObject(['qty' => $qty]),
            ]);
        }

        $this->type->method('getChildrenIds')->willReturn($skusById ? [3 => array_keys($skusById)] : []);
        $this->getSkusByProductIds->method('execute')->willReturn($skusById);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getId')->willReturn(1);
        $product->method('getTypeInstance')->willReturn($this->type);
        $product->method('getProductLinks')->willReturn($links);
        $this->productRepository->method('get')->willReturn($product);
    }
}
