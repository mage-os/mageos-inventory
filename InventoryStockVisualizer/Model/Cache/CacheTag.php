<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
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
