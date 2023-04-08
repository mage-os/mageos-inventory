<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\CatalogInventory\Model\Configuration;
use Magento\Framework\App\Config\Value;
use Magento\TestFramework\App\Config as AppConfig;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var Value $value */
$value = $objectManager->get(Value::class);
$value->setPath(Configuration::XML_PATH_MANAGE_STOCK);
$value->setValue('0');
$value->save();

/** @var AppConfig $appConfig */
$appConfig = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(AppConfig::class);
$appConfig->clean();
