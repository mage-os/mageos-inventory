<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryDistanceBasedSourceSelection\Model\Convert;

use Magento\InventorySourceSelectionApi\Api\Data\AddressInterface;

class AddressToComponentsString
{
    /**
     * Get components string from address
     *
     * @param AddressInterface $address
     * @return string
     */
    public function execute(AddressInterface $address): string
    {
        return implode('|', [
            'country:' . $address->getCountry(),
//            'postal_code:' . $address->getPostcode(),
            'locality:' . $address->getCity(),
        ]);
    }
}
