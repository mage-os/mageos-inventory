<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Product\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Per-product display-type options, including a "use config default" empty value.
 */
class DisplayType extends AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('Use config default')],
                ['value' => Config::DISPLAY_TYPE_LEVEL, 'label' => __('Level (semaphore)')],
                ['value' => Config::DISPLAY_TYPE_QUANTITY, 'label' => __('Exact quantity')],
            ];
        }

        return $this->_options;
    }
}
