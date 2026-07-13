<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\InventoryCatalog\DetectOversellOnBulkSourceUnassignPlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnBulkSourceUnassignPluginTest extends TestCase
{
    public function testChecksEveryUnassignedPairAndPreservesResult(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $plugin = new DetectOversellOnBulkSourceUnassignPlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(BulkSourceUnassign::class),
            3,
            ['sku-1'],
            ['src-a', 'src-b']
        );

        self::assertSame(3, $result);
        self::assertSame([
            ['source_code' => 'src-a', 'sku' => 'sku-1'],
            ['source_code' => 'src-b', 'sku' => 'sku-1'],
        ], $captured);
    }
}
