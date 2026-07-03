<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryInStorePickupQuote\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressExtensionFactory;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Clear pickup location data from quote shipping address when switching to a non-pickup delivery method.
 */
class ClearPickupLocationOnNonPickupShippingMethodPlugin
{
    /**
     * @param AddressExtensionFactory $addressExtensionFactory
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly AddressExtensionFactory $addressExtensionFactory,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * Remove pickup location association before shipping information is saved for a delivery method.
     *
     * @param ShippingInformationManagement $subject
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws NoSuchEntityException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $carrierCode = $addressInformation->getShippingCarrierCode();
        $methodCode = $addressInformation->getShippingMethodCode();
        if ($carrierCode === null || $methodCode === null) {
            return [$cartId, $addressInformation];
        }
        if ($carrierCode . '_' . $methodCode === InStorePickup::DELIVERY_METHOD) {
            return [$cartId, $addressInformation];
        }
        $quote = $this->cartRepository->getActive($cartId);
        $quoteShippingAddress = $quote->getShippingAddress();
        $shippingAddress = $addressInformation->getShippingAddress();
        $shouldResetPickupAddress = $this->shouldResetInStorePickupAddress($shippingAddress, $quoteShippingAddress);
        $this->clearPickupLocationFromAddress($shippingAddress);
        if ($quoteShippingAddress !== null && $quoteShippingAddress !== $shippingAddress) {
            $this->clearPickupLocationFromAddress($quoteShippingAddress);
        }
        if ($shouldResetPickupAddress) {
            $this->resetInStorePickupAddress($shippingAddress);
            if ($quoteShippingAddress !== null && $quoteShippingAddress !== $shippingAddress) {
                $this->resetInStorePickupAddress($quoteShippingAddress);
            }
        }
        return [$cartId, $addressInformation];
    }

    /**
     * Check if the in-store pickup address should be clear or not
     *
     * @param AddressInterface|null $shippingAddress
     * @param AddressInterface|null $quoteShippingAddress
     * @return bool
     */
    private function shouldResetInStorePickupAddress(
        ?AddressInterface $shippingAddress,
        ?AddressInterface $quoteShippingAddress
    ): bool {
        if ($this->hasPickupLocationCode($shippingAddress)) {
            return true;
        }
        if ($this->hasPickupLocationCode($quoteShippingAddress)) {
            return true;
        }
        return $quoteShippingAddress?->getShippingMethod() === InStorePickup::DELIVERY_METHOD;
    }

    /**
     * Check the given address is an in-store pickup address
     *
     * @param AddressInterface|null $address
     * @return bool
     */
    private function hasPickupLocationCode(?AddressInterface $address): bool
    {
        $pickupLocationCode = $address?->getExtensionAttributes()?->getPickupLocationCode();

        return $pickupLocationCode !== null && $pickupLocationCode !== '';
    }

    /**
     * Clear in-store pickup address fields
     *
     * @param AddressInterface|null $address
     * @return void
     */
    private function resetInStorePickupAddress(?AddressInterface $address): void
    {
        if ($address === null) {
            return;
        }
        $address->setFirstname(null);
        $address->setLastname(null);
        $address->setCompany(null);
        $address->setStreet([]);
        $address->setCity(null);
        $address->setRegion(null);
        $address->setRegionId(null);
        $address->setRegionCode(null);
        $address->setPostcode(null);
        $address->setTelephone(null);
        $address->setCustomerAddressId(null);
        $address->setSaveInAddressBook(false);
        $address->setSameAsBilling(false);
    }

    /**
     * Ensure extension attributes exist and clear pickup location code.
     *
     * @param AddressInterface|null $address
     * @return void
     */
    private function clearPickupLocationFromAddress(?AddressInterface $address): void
    {
        if ($address === null) {
            return;
        }
        $extension = $address->getExtensionAttributes();
        if ($extension === null) {
            $extension = $this->addressExtensionFactory->create();
            $address->setExtensionAttributes($extension);
        }
        $extension->setPickupLocationCode(null);
    }
}
