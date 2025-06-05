<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotification\Model\SourceItemConfiguration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryLowQuantityNotificationApi\Api\Data\SourceItemConfigurationInterface;

/**
 * Get default configuration values from system config
 */
class GetDefaultValues
{
    /**
     * Default Notify Stock Qty config path
     */
    public const XML_PATH_NOTIFY_STOCK_QTY = 'cataloginventory/item_options/notify_stock_qty';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Returns default source item configuration values, including source code, SKU, and notify stock quantity.
     *
     * @param string $sourceCode
     * @param string $sku
     * @return array
     */
    public function execute(string $sourceCode, string $sku): array
    {
        $inventoryNotifyQty = (float)$this->scopeConfig->getValue(self::XML_PATH_NOTIFY_STOCK_QTY);

        $defaultConfiguration = [
            SourceItemConfigurationInterface::SOURCE_CODE => $sourceCode,
            SourceItemConfigurationInterface::SKU => $sku,
            SourceItemConfigurationInterface::INVENTORY_NOTIFY_QTY => $inventoryNotifyQty,
        ];
        return $defaultConfiguration;
    }
}
