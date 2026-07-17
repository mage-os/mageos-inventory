<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;

/**
 * Resolve, for a set of source codes, every stock they are linked to.
 *
 * A source can belong to more than one stock, so a single source-item write may affect several
 * stocks; the caller needs all of them to decide per-stock whether the displayed level changed.
 */
class ResolveStockIdsBySourceCodes
{
    /**
     * @param GetStockSourceLinksInterface $getStockSourceLinks
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly GetStockSourceLinksInterface $getStockSourceLinks,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @param string[] $sourceCodes
     * @return array<string, int[]>
     */
    public function execute(array $sourceCodes): array
    {
        $sourceCodes = array_map('strval', $sourceCodes);
        $sourceCodes = array_values(
            array_unique(array_filter($sourceCodes, static fn (string $code): bool => $code !== ''))
        );
        if (!$sourceCodes) {
            return [];
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(StockSourceLinkInterface::SOURCE_CODE, $sourceCodes, 'in')
            ->create();

        $result = [];
        foreach ($this->getStockSourceLinks->execute($searchCriteria)->getItems() as $link) {
            $result[(string) $link->getSourceCode()][] = (int) $link->getStockId();
        }

        return $result;
    }
}
