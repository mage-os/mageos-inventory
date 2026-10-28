<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Availability;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Simple;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryStockVisualizer\Model\Availability\GetCompositeChildren;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see GetCompositeChildren
 */
class GetCompositeChildrenTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private $productRepository;

    /**
     * @var GetCompositeChildren
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->model = new GetCompositeChildren($this->productRepository);
    }

    /**
     * An unknown SKU yields no children instead of throwing.
     *
     * @return void
     */
    public function testUnknownSkuReturnsEmpty(): void
    {
        $this->productRepository->method('get')->willThrowException(new NoSuchEntityException());

        $this->assertSame([], $this->model->execute('missing'));
    }

    /**
     * A non-composite type yields no children.
     *
     * @return void
     */
    public function testNonCompositeReturnsEmpty(): void
    {
        $this->productRepository->method('get')->willReturn($this->parentWithType(Simple::class));

        $this->assertSame([], $this->model->execute('SIMPLE-1'));
    }

    /**
     * Configurable children are mapped to sku/label rows and de-duplicated by SKU.
     *
     * @return void
     */
    public function testConfigurableChildrenMappedAndDeduped(): void
    {
        $type = $this->createMock(Configurable::class);
        $type->method('getUsedProducts')->willReturn([
            $this->child('VAR-1', 'Variant One'),
            $this->child('VAR-2', null),
            $this->child('VAR-1', 'Duplicate'),
        ]);
        $this->productRepository->method('get')->willReturn($this->parentWithType($type));

        $rows = $this->model->execute('CONF-1');

        $this->assertSame(
            [
                ['sku' => 'VAR-1', 'label' => 'Variant One'],
                ['sku' => 'VAR-2', 'label' => 'VAR-2'],
            ],
            $rows
        );
    }

    /**
     * @param object|string $type A type-instance mock or a class name to mock.
     * @return ProductInterface|MockObject
     */
    private function parentWithType($type)
    {
        $typeInstance = is_string($type) ? $this->createMock($type) : $type;
        $product = $this->createMock(Product::class);
        $product->method('getTypeInstance')->willReturn($typeInstance);

        return $product;
    }

    /**
     * @param string $sku
     * @param string|null $name
     * @return Product|MockObject
     */
    private function child(string $sku, ?string $name)
    {
        $child = $this->createMock(Product::class);
        $child->method('getSku')->willReturn($sku);
        $child->method('getName')->willReturn($name);

        return $child;
    }
}
