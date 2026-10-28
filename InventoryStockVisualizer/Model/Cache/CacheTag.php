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
 * The tag keeps the purge blast radius to the AJAX fragments. It does NOT isolate the
 * product page in level mode: there the block reports the tag as a page identity, so a
 * purge invalidates the whole page. Invalidating the page on a salability flip is
 * Magento_InventoryCache's job (it owns the cat_p_<id> flush) — this module never
 * registers cat_p itself, and quantity mode relies on that entirely.
 */
class CacheTag
{
    public const CACHE_TAG = 'inv_stockviz';
}
