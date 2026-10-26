<?php
/**
 * Copyright 2026 Mage-OS
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Observer\RemoveCoreStockBlocks;
use PHPUnit\Framework\TestCase;

class RemoveCoreStockBlocksTest extends TestCase
{
    public function testKeepsCoreStockStatusWhenDisabled(): void
    {
        $layout = $this->layout(['product.info.stock.visualizer', 'product.info.simple']);
        $layout->expects($this->never())->method('unsetElement');

        $this->observer(false)->execute($this->event($layout));
    }

    public function testKeepsCoreStockStatusOutsideTheProductPage(): void
    {
        $layout = $this->layout(['product.info.simple']);
        $layout->expects($this->never())->method('unsetElement');

        $this->observer(true)->execute($this->event($layout));
    }

    public function testRemovesOnlyThePresentCoreStockBlocksWhenEnabled(): void
    {
        $layout = $this->layout(['product.info.stock.visualizer', 'product.info.configurable']);
        $layout->expects($this->once())->method('unsetElement')->with('product.info.configurable');

        $this->observer(true)->execute($this->event($layout));
    }

    /**
     * @param bool $enabled
     * @return RemoveCoreStockBlocks
     */
    private function observer(bool $enabled): RemoveCoreStockBlocks
    {
        return new RemoveCoreStockBlocks($this->createConfiguredStub(Config::class, ['isEnabled' => $enabled]));
    }

    /**
     * @param string[] $blockNames
     * @return LayoutInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function layout(array $blockNames): LayoutInterface
    {
        $block = $this->createStub(AbstractBlock::class);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->willReturnCallback(
            fn(string $name) => in_array($name, $blockNames, true) ? $block : false
        );

        return $layout;
    }

    /**
     * @param LayoutInterface $layout
     * @return Observer
     */
    private function event(LayoutInterface $layout): Observer
    {
        return new Observer(['event' => new Event(['layout' => $layout])]);
    }
}
