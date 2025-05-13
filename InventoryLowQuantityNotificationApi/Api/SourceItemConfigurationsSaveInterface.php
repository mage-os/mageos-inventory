<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotificationApi\Api;

use Magento\InventoryLowQuantityNotificationApi\Api\Data\SourceItemConfigurationInterface;

/**
 * Save the source item configuration
 *
 * Used fully qualified namespaces in annotations for proper work of WebApi request parser
 *
 * @api
 */
interface SourceItemConfigurationsSaveInterface
{
    /**
     * Saves source item configurations via WebAPI, validates input, and ensures data consistency for inventory updates.
     *
     * @param SourceItemConfigurationInterface[] $sourceItemConfigurations
     * @return void
     */
    public function execute(array $sourceItemConfigurations): void;
}
