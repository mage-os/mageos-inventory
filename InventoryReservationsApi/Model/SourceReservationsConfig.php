<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservationsApi\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Provides the state of the source-level reservations feature.
 *
 * @api
 */
class SourceReservationsConfig
{
    public const XML_PATH_SOURCE_RESERVATIONS_ENABLED = 'cataloginventory/source_reservations/enabled';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check whether source-level reservations are enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_SOURCE_RESERVATIONS_ENABLED);
    }
}
