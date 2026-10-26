<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Etc;

use Magento\InventorySales\Model\AppendReservations;
use Magento\InventorySales\Plugin\InventorySales\LockReservedSkusPlugin;
use Magento\InventorySales\Plugin\Quote\CartManagementPlugin;
use Magento\Quote\Model\QuoteManagement;
use PHPUnit\Framework\TestCase;

class PlaceOrderLockWiringTest extends TestCase
{
    /**
     * @var \DOMXPath
     */
    private $xpath;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $document = new \DOMDocument();
        $document->load(dirname(__DIR__, 3) . '/etc/di.xml');
        $this->xpath = new \DOMXPath($document);
    }

    public function testTheReservationIsWrappedByTheLockPlugin(): void
    {
        $plugin = $this->plugin(AppendReservations::class, 'lock_reserved_skus');

        $this->assertSame(LockReservedSkusPlugin::class, $plugin->getAttribute('type'));
        $this->assertNotSame('true', $plugin->getAttribute('disabled'));
    }

    public function testThePlaceOrderLockIsDisabled(): void
    {
        $plugin = $this->plugin(QuoteManagement::class, 'inventory_lock_during_place_order');

        $this->assertSame(CartManagementPlugin::class, $plugin->getAttribute('type'));
        $this->assertSame('true', $plugin->getAttribute('disabled'));
    }

    /**
     * @param string $type
     * @param string $name
     * @return \DOMElement
     */
    private function plugin(string $type, string $name): \DOMElement
    {
        $plugins = $this->xpath->query(sprintf('//type[@name="%s"]/plugin[@name="%s"]', $type, $name));

        $this->assertSame(1, $plugins->length, "$name must be declared once on $type");

        return $plugins->item(0);
    }
}
