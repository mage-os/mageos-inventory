<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Plugin\InventoryCatalog\DetectOversellOnTransferInventoryPartiallyPlugin;
use PHPUnit\Framework\TestCase;

class DetectOversellOnTransferInventoryPartiallyPluginTest extends TestCase
{
    public function testChecksOriginAndDestinationForTransferredSku(): void
    {
        $detect = $this->createMock(DetectSourceItemsOversell::class);
        $captured = null;
        $detect->method('execute')->willReturnCallback(function (array $pairs) use (&$captured) {
            $captured = $pairs;
            return [];
        });

        $transfer = $this->createMock(PartialInventoryTransferItemInterface::class);
        $transfer->method('getSku')->willReturn('sku-1');

        $plugin = new DetectOversellOnTransferInventoryPartiallyPlugin($detect);
        $result = $plugin->afterExecute(
            $this->createMock(TransferInventoryPartially::class),
            null,
            $transfer,
            'origin',
            'destination'
        );

        self::assertNull($result);
        self::assertSame([
            ['source_code' => 'origin', 'sku' => 'sku-1'],
            ['source_code' => 'destination', 'sku' => 'sku-1'],
        ], $captured);
    }
}
