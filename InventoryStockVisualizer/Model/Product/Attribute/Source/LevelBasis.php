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
 * Per-product level-basis options, including a "use config default" empty value.
 */
class LevelBasis extends AbstractSource
{
    /**
     * @inheritdoc
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('Use config default')],
                ['value' => Config::LEVEL_BASIS_QUANTITY, 'label' => __('By quantity')],
                ['value' => Config::LEVEL_BASIS_PERCENTAGE, 'label' => __('By percentage')],
            ];
        }

        return $this->_options;
    }
}
