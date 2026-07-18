<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

/**
 * Availability level values for the semaphore (traffic-light) display.
 */
class Level
{
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';
    public const OUT = 'out';
}
