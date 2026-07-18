<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\Notification\NotifierInterface;
use Psr\Log\LoggerInterface;

/**
 * Surface an oversold supply condition (physical stock below committed
 * reservations). Detection never blocks the write, so this is the only output:
 * a machine-parseable structured log per position and one summarized admin
 * inbox notice per batch. Best-effort — a failure to alert must never propagate
 * back to the stock write that triggered the check.
 */
class OversellNotifier
{
    private const NOTICE_SAMPLE = 5;

    /**
     * @param LoggerInterface $logger
     * @param NotifierInterface $notifier
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly NotifierInterface $notifier
    ) {
    }

    /**
     * Log every oversold position and push a single summarized admin notice.
     *
     * @param array $oversold
     * @return void
     */
    public function notify(array $oversold): void
    {
        if (empty($oversold)) {
            return;
        }

        foreach ($oversold as $item) {
            $this->logger->warning(
                'Source-level reservations: physical stock is below committed reservations.',
                [
                    'source_code' => $item['source_code'],
                    'sku' => $item['sku'],
                    'physical' => $item['physical'],
                    'committed' => -$item['reserved'],
                    'oversold_by' => -$item['delta'],
                ]
            );
        }

        $this->addAdminNotice($oversold);
    }

    /**
     * Push one summarized notice to the admin inbox, swallowing any failure.
     *
     * @param array $oversold
     * @return void
     */
    private function addAdminNotice(array $oversold): void
    {
        try {
            $this->notifier->addMajor(
                (string)__('%1 source stock position(s) oversold', count($oversold)),
                $this->describe($oversold)
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Source-level reservations: could not push the oversell admin notification.',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Build a bounded human-readable summary of the oversold positions.
     *
     * @param array $oversold
     * @return string
     */
    private function describe(array $oversold): string
    {
        $lines = [];
        foreach (array_slice($oversold, 0, self::NOTICE_SAMPLE) as $item) {
            $lines[] = (string)__(
                'SKU "%1" on source "%2": %3 in stock, %4 committed (oversold by %5).',
                $item['sku'],
                $item['source_code'],
                $item['physical'],
                -$item['reserved'],
                -$item['delta']
            );
        }
        if (count($oversold) > self::NOTICE_SAMPLE) {
            $lines[] = (string)__('and %1 more.', count($oversold) - self::NOTICE_SAMPLE);
        }

        return implode(' ', $lines);
    }
}
