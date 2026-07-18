<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\InventoryCatalog\DetectOversellOnBulkInventoryTransferPlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnBulkInventoryTransferPluginTest extends TestCase
{
    public function testChecksOriginAndDestinationForEverySku(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $plugin = new DetectOversellOnBulkInventoryTransferPlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(BulkInventoryTransfer::class),
            null,
            ['sku-1', 'sku-2'],
            'origin',
            'destination',
            true
        );

        self::assertNull($result);
        self::assertSame([
            ['source_code' => 'origin', 'sku' => 'sku-1'],
            ['source_code' => 'destination', 'sku' => 'sku-1'],
            ['source_code' => 'origin', 'sku' => 'sku-2'],
            ['source_code' => 'destination', 'sku' => 'sku-2'],
        ], $captured);
    }
}
