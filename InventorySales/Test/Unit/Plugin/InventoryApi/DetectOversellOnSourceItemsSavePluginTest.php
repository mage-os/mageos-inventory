<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\InventoryApi\DetectOversellOnSourceItemsSavePlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnSourceItemsSavePluginTest extends TestCase
{
    public function testPassesSavedPairsToDetector(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $plugin = new DetectOversellOnSourceItemsSavePlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('src-a', 'sku-1'), $this->sourceItem('src-b', 'sku-2')]
        );

        self::assertNull($result);
        self::assertSame([
            ['source_code' => 'src-a', 'sku' => 'sku-1'],
            ['source_code' => 'src-b', 'sku' => 'sku-2'],
        ], $captured);
    }

    private function sourceItem(string $source, string $sku): SourceItemInterface
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn($source);
        $sourceItem->method('getSku')->willReturn($sku);

        return $sourceItem;
    }
}
