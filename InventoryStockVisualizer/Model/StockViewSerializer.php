<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;

/**
 * Project an availability view into the minimal quantity payload for the AJAX
 * fragment: the aggregate quantity plus, in per-source scope, a compact
 * source_code => qty map. Source names/labels are server-rendered, not sent here.
 */
class StockViewSerializer
{
    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param StockViewInterface $view
     * @return array
     */
    public function serialize(StockViewInterface $view): array
    {
        $data = ['qty' => $view->getSalableQty()];

        if ($this->config->getScope() === Config::SCOPE_PER_SOURCE) {
            $sources = [];
            foreach ($view->getSources() as $source) {
                $sources[$source->getSourceCode()] = $source->getQty();
            }
            $data['sources'] = $sources;
        }

        return $data;
    }
}
