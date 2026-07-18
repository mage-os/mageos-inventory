<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\Inventory;

use Magento\Inventory\Model\SourceItem\Command\DecrementSourceItemQty;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\Inventory\DetectOversellOnDecrementSourceItemQtyPlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnDecrementSourceItemQtyPluginTest extends TestCase
{
    public function testExtractsPairsFromDecrementData(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $plugin = new DetectOversellOnDecrementSourceItemQtyPlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(DecrementSourceItemQty::class),
            null,
            [
                ['source_item' => $this->sourceItem('src-a', 'sku-1'), 'qty_to_decrement' => 2.0],
                ['qty_to_decrement' => 5.0],
            ]
        );

        self::assertNull($result);
        self::assertSame([['source_code' => 'src-a', 'sku' => 'sku-1']], $captured);
    }

    private function sourceItem(string $source, string $sku): SourceItemInterface
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn($source);
        $sourceItem->method('getSku')->willReturn($sku);

        return $sourceItem;
    }
}
