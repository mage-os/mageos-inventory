<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceAssign;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\InventoryCatalog\DetectOversellOnBulkSourceAssignPlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnBulkSourceAssignPluginTest extends TestCase
{
    public function testChecksEveryAssignedPairAndPreservesResult(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $plugin = new DetectOversellOnBulkSourceAssignPlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(BulkSourceAssign::class),
            7,
            ['sku-1', 'sku-2'],
            ['src-a']
        );

        self::assertSame(7, $result);
        self::assertSame([
            ['source_code' => 'src-a', 'sku' => 'sku-1'],
            ['source_code' => 'src-a', 'sku' => 'sku-2'],
        ], $captured);
    }
}
