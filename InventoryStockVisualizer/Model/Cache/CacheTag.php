<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

/**
 * Dedicated cache tag for the stock visualizer fragment.
 *
 * A dedicated tag keeps the purge blast radius to the fragment only, leaving the
 * surrounding product page cache intact.
 */
class CacheTag
{
    public const CACHE_TAG = 'inv_stockviz';
}
