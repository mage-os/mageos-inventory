<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Cron;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOversoldSourceItems;
use Magento\InventorySales\Model\SourceReservation\OversellDetectionConfig;
use Magento\InventorySales\Model\SourceReservation\OversellNotifier;
use Psr\Log\LoggerInterface;

/**
 * Periodic vector-agnostic oversell sweep. Opt-in; the schedule is read from a
 * configurable cron expression. Bounded per run; a hit limit is logged so a
 * growing set of oversold positions is visible instead of silently truncated.
 */
class DetectOversell
{
    private const BATCH_LIMIT = 1000;

    /**
     * @param OversellDetectionConfig $config
     * @param GetOversoldSourceItems $getOversoldSourceItems
     * @param OversellNotifier $notifier
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OversellDetectionConfig $config,
        private readonly GetOversoldSourceItems $getOversoldSourceItems,
        private readonly OversellNotifier $notifier,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the sweep when enabled.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isSweepEnabled()) {
            return;
        }

        $oversold = $this->getOversoldSourceItems->execute(self::BATCH_LIMIT);
        if (empty($oversold)) {
            return;
        }

        $this->notifier->notify($oversold);
        if (count($oversold) >= self::BATCH_LIMIT) {
            $this->logger->warning(
                'Source-level reservations: oversell sweep hit its batch limit; more positions may be oversold.',
                ['limit' => self::BATCH_LIMIT]
            );
        }
    }
}
