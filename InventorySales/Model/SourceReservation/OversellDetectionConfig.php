<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

/**
 * Provides the state of the supply-side oversell detection. Every layer is a
 * no-op unless source-level reservations are enabled AND its own toggle is on.
 */
class OversellDetectionConfig
{
    public const XML_PATH_DETECTION_ENABLED = 'cataloginventory/source_reservations/oversell_detection_enabled';
    public const XML_PATH_SWEEP_ENABLED = 'cataloginventory/source_reservations/oversell_sweep_enabled';
    public const XML_PATH_SWEEP_CRON = 'cataloginventory/source_reservations/oversell_sweep_cron';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
    }

    /**
     * Whether the real-time oversell detection on stock writes is enabled.
     *
     * @return bool
     */
    public function isDetectionEnabled(): bool
    {
        return $this->sourceReservationsConfig->isEnabled()
            && $this->scopeConfig->isSetFlag(self::XML_PATH_DETECTION_ENABLED);
    }

    /**
     * Whether the periodic oversell detection sweep is enabled.
     *
     * @return bool
     */
    public function isSweepEnabled(): bool
    {
        return $this->sourceReservationsConfig->isEnabled()
            && $this->scopeConfig->isSetFlag(self::XML_PATH_SWEEP_ENABLED);
    }
}
