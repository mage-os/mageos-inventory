<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Provides the state of the reservation reconciliation safety nets.
 */
class ReconciliationConfig
{
    public const XML_PATH_CANCEL_REFUND = 'cataloginventory/source_reservations/reconcile_cancel_refund';
    public const XML_PATH_SWEEP_ENABLED = 'cataloginventory/source_reservations/reconcile_sweep_enabled';
    public const XML_PATH_SWEEP_CRON = 'cataloginventory/source_reservations/reconcile_sweep_cron';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether missing releases are reconciled synchronously on a terminal state change.
     *
     * @return bool
     */
    public function isCancelRefundReconciliationEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CANCEL_REFUND);
    }

    /**
     * Whether the periodic out-of-band reconciliation sweep is enabled.
     *
     * @return bool
     */
    public function isSweepEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SWEEP_ENABLED);
    }
}
