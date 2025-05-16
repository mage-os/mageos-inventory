<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotificationApi\Api;

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
     * @param \Magento\InventoryLowQuantityNotificationApi\Api\Data\SourceItemConfigurationInterface[]
     *      $sourceItemConfigurations
     * @return void
     */
    public function execute(array $sourceItemConfigurations): void;
}
