<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Psr\Log\LoggerInterface;

/**
 * Detect, for a targeted set of (source, sku) pairs, whether physical stock has
 * dropped below the reservations already committed against that source. Physical
 * stock is authoritative, so this NEVER blocks or rewrites the change that
 * triggered it: an oversold position is surfaced for reconciliation and the write
 * proceeds. Any failure is swallowed so detection can never break a stock write.
 */
class DetectSourceItemsOversell
{
    private const EPSILON = 0.000001;

    /**
     * @param OversellDetectionConfig $config
     * @param GetReservationsQuantityBySkusAndSources $getReservationsQuantity
     * @param GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity
     * @param OversellNotifier $notifier
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OversellDetectionConfig $config,
        private readonly GetReservationsQuantityBySkusAndSources $getReservationsQuantity,
        private readonly GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity,
        private readonly OversellNotifier $notifier,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check the given pairs and alert on any oversold position.
     *
     * @param array $pairs
     * @return array<int, array{source_code:string, sku:string, physical:float, reserved:float, delta:float}>
     */
    public function execute(array $pairs): array
    {
        if (!$this->config->isDetectionEnabled()) {
            return [];
        }

        try {
            return $this->detect($pairs);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Source-level reservations: oversell detection failed.',
                ['exception' => $e->getMessage()]
            );

            return [];
        }
    }

    /**
     * Compare committed reservations against physical stock for the distinct pairs.
     *
     * @param array $pairs
     * @return array
     */
    private function detect(array $pairs): array
    {
        $skus = [];
        $sources = [];
        $wanted = [];
        foreach ($pairs as $pair) {
            $source = (string)($pair['source_code'] ?? '');
            $sku = (string)($pair['sku'] ?? '');
            if ($source === '' || $sku === '') {
                continue;
            }
            $skus[$sku] = true;
            $sources[$source] = true;
            $wanted[$source . '|' . $sku] = ['source_code' => $source, 'sku' => $sku];
        }
        if (empty($wanted)) {
            return [];
        }

        $skus = array_keys($skus);
        $sources = array_keys($sources);
        $reservations = $this->getReservationsQuantity->execute($skus, $sources);
        $physical = $this->getSourceItemQuantity->execute($skus, $sources);

        $oversold = [];
        foreach ($wanted as $entry) {
            $source = $entry['source_code'];
            $sku = $entry['sku'];
            $reserved = $reservations[$source][$sku] ?? 0.0;
            $inStock = $physical[$source][$sku] ?? 0.0;
            $delta = $inStock + $reserved;
            if ($delta < -self::EPSILON) {
                $oversold[] = [
                    'source_code' => $source,
                    'sku' => $sku,
                    'physical' => $inStock,
                    'reserved' => $reserved,
                    'delta' => $delta,
                ];
            }
        }

        if (!empty($oversold)) {
            $this->notifier->notify($oversold);
        }

        return $oversold;
    }
}
