<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Availability;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryStockVisualizer\Model\Availability\GetBundleMaxSellable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see GetBundleMaxSellable
 */
class GetBundleMaxSellableTest extends TestCase
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
     * @var BundleType|MockObject
     */
    private $type;

    /**
     * @var GetBundleMaxSellable
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
        $this->type = $this->createMock(BundleType::class);

        $product = $this->createMock(Product::class);
        $product->method('getTypeInstance')->willReturn($this->type);
        $this->productRepository->method('get')->willReturn($product);
        $this->type->method('getOptionsIds')->willReturn([1, 2]);

        $this->model = new GetBundleMaxSellable($this->productRepository, $this->getProductSalableQty);
    }

    /**
     * One required option: max is floor(childSalable / perBundleQty).
     *
     * @return void
     */
    public function testSingleRequiredOption(): void
    {
        $this->givenOptions([1 => true]);
        $this->givenSelections([
            ['id' => 11, 'option' => 1, 'sku' => 'A', 'qty' => 2.0, 'changeable' => false],
        ]);
        $this->getProductSalableQty->method('execute')->willReturn(10.0);

        $result = $this->model->execute('BUNDLE-1', [11 => 1], self::STOCK_ID);
        $this->assertSame(5, $result->getMax());
        $this->assertSame([1011], $result->getProductIds());
    }

    /**
     * The result is the minimum across all chosen selections/options.
     *
     * @return void
     */
    public function testMinAcrossOptions(): void
    {
        $this->givenOptions([1 => true, 2 => true]);
        $this->givenSelections([
            ['id' => 11, 'option' => 1, 'sku' => 'A', 'qty' => 1.0, 'changeable' => false],
            ['id' => 21, 'option' => 2, 'sku' => 'B', 'qty' => 2.0, 'changeable' => false],
        ]);
        $this->getProductSalableQty->method('execute')->willReturnMap([
            ['A', self::STOCK_ID, 9.0],
            ['B', self::STOCK_ID, 8.0],
        ]);

        // A: floor(9/1)=9 ; B: floor(8/2)=4 => min 4
        $result = $this->model->execute('BUNDLE-1', [11 => 1, 21 => 1], self::STOCK_ID);
        $this->assertSame(4, $result->getMax());
        $this->assertSame([1011, 1021], $result->getProductIds());
    }

    /**
     * A required option with no chosen selection yields null (prompt to finish selecting).
     *
     * @return void
     */
    public function testRequiredOptionUnselectedReturnsNull(): void
    {
        $this->givenOptions([1 => true, 2 => true]);
        $this->givenSelections([
            ['id' => 11, 'option' => 1, 'sku' => 'A', 'qty' => 1.0, 'changeable' => false],
            ['id' => 21, 'option' => 2, 'sku' => 'B', 'qty' => 1.0, 'changeable' => false],
        ]);
        $this->getProductSalableQty->method('execute')->willReturn(10.0);

        // Only option 1 chosen; option 2 is required but unchosen.
        $result = $this->model->execute('BUNDLE-1', [11 => 1], self::STOCK_ID);
        $this->assertNull($result->getMax());
        $this->assertSame([], $result->getProductIds());
    }

    /**
     * Changeable qty uses the customer's per-bundle quantity.
     *
     * @return void
     */
    public function testChangeableQtyUsesCustomerQty(): void
    {
        $this->givenOptions([1 => true]);
        $this->givenSelections([
            ['id' => 11, 'option' => 1, 'sku' => 'A', 'qty' => 1.0, 'changeable' => true],
        ]);
        $this->getProductSalableQty->method('execute')->willReturn(12.0);

        // customer qty 3 => floor(12/3)=4
        $this->assertSame(4, $this->model->execute('BUNDLE-1', [11 => 3], self::STOCK_ID)->getMax());
    }

    /**
     * An optional, unselected option does not constrain the result.
     *
     * @return void
     */
    public function testOptionalUnselectedIgnored(): void
    {
        $this->givenOptions([1 => true, 2 => false]);
        $this->givenSelections([
            ['id' => 11, 'option' => 1, 'sku' => 'A', 'qty' => 1.0, 'changeable' => false],
            ['id' => 21, 'option' => 2, 'sku' => 'B', 'qty' => 1.0, 'changeable' => false],
        ]);
        $this->getProductSalableQty->method('execute')->willReturn(7.0);

        // Option 2 optional and not chosen; only option 1 constrains => 7
        $result = $this->model->execute('BUNDLE-1', [11 => 1], self::STOCK_ID);
        $this->assertSame(7, $result->getMax());
        $this->assertSame([1011], $result->getProductIds());
    }

    /**
     * @param array<int, bool> $requiredByOptionId
     * @return void
     */
    private function givenOptions(array $requiredByOptionId): void
    {
        $options = [];
        foreach ($requiredByOptionId as $optionId => $required) {
            $options[] = new DataObject(['option_id' => $optionId, 'required' => $required]);
        }
        $this->type->method('getOptionsCollection')->willReturn($options);
    }

    /**
     * @param array<int, array{id: int, option: int, sku: string, qty: float, changeable: bool}> $selections
     * @return void
     */
    private function givenSelections(array $selections): void
    {
        $rows = [];
        foreach ($selections as $s) {
            $rows[] = new DataObject([
                'selection_id' => $s['id'],
                'option_id' => $s['option'],
                'product_id' => $s['pid'] ?? $s['id'] + 1000,
                'sku' => $s['sku'],
                'selection_qty' => $s['qty'],
                'selection_can_change_qty' => $s['changeable'],
            ]);
        }
        $this->type->method('getSelectionsCollection')->willReturn($rows);
    }
}
