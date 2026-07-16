<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryStockVisualizer\Model\Product\Attribute\Source\DisplayType;
use Magento\InventoryStockVisualizer\Model\Product\Attribute\Source\LevelBasis;
use Magento\InventoryStockVisualizer\Model\Product\StockVisualizerAttributes as Attr;

/**
 * Add the per-product stock-visualizer override attributes.
 */
class AddStockVisualizerAttributes implements DataPatchInterface
{
    private const GROUP = 'Stock Visualizer';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(Product::ENTITY, Attr::DISPLAY_TYPE, [
            'type' => 'varchar',
            'input' => 'select',
            'label' => 'Stock Visualizer Display Type',
            'source' => DisplayType::class,
            'required' => false,
            'user_defined' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'group' => self::GROUP,
            'visible' => true,
            'used_in_product_listing' => false,
            'default' => '',
            'sort_order' => 10,
        ]);

        $eavSetup->addAttribute(Product::ENTITY, Attr::LEVEL_BASIS, [
            'type' => 'varchar',
            'input' => 'select',
            'label' => 'Stock Visualizer Level Basis',
            'source' => LevelBasis::class,
            'required' => false,
            'user_defined' => false,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'group' => self::GROUP,
            'visible' => true,
            'used_in_product_listing' => false,
            'default' => '',
            'sort_order' => 20,
        ]);

        foreach ([
            Attr::LEVEL_HIGH => ['label' => 'Stock Visualizer High Threshold', 'sort' => 30],
            Attr::LEVEL_LOW => ['label' => 'Stock Visualizer Low Threshold', 'sort' => 40],
            Attr::FULL_QTY => ['label' => 'Stock Visualizer Full Quantity (percentage reference)', 'sort' => 50],
        ] as $code => $meta) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => 'decimal',
                'input' => 'text',
                'label' => $meta['label'],
                'required' => false,
                'user_defined' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => self::GROUP,
                'visible' => true,
                'used_in_product_listing' => false,
                'sort_order' => $meta['sort'],
            ]);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
