<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryInStorePickupQuoteGraphQl\Test\Unit\Plugin\Checkout;

use Magento\Quote\Api\Data\AddressExtensionInterface;

/**
 * Test stub that declares the pickup location extension attribute methods.
 *
 * The source AddressExtensionInterface is an empty marker; getPickupLocationCode()/
 * setPickupLocationCode() only exist on the generated interface. Mocking this stub keeps
 * the unit test independent of generated code and PHPUnit version.
 */
interface AddressExtensionStubInterface extends AddressExtensionInterface
{
    /**
     * Get pickup location code.
     *
     * @return string|null
     */
    public function getPickupLocationCode();

    /**
     * Set pickup location code.
     *
     * @param string|null $pickupLocationCode
     * @return $this
     */
    public function setPickupLocationCode($pickupLocationCode);
}
