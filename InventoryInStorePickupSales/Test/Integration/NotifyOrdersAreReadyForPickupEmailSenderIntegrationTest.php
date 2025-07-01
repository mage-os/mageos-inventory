<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryInStorePickupSales\Test\Integration\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventoryInStorePickupSales\Model\NotifyOrdersAreReadyForPickupEmailSender;
use Magento\InventoryInStorePickupSales\Model\Order\Email\ReadyForPickupSender;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class NotifyOrdersAreReadyForPickupEmailSenderIntegrationTest extends TestCase
{
    /**
     * @var ReadyForPickupSender
     */
    private $emailSender;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private $orderStatusHistoryRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var NotifyOrdersAreReadyForPickupEmailSender
     */
    private $notifyOrdersAreReadyForPickupEmailSender;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $this->emailSender = $objectManager->get(ReadyForPickupSender::class);
        $this->orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->config = $objectManager->get(ScopeConfigInterface::class);
        $this->orderStatusHistoryRepository = $objectManager->get(OrderStatusHistoryRepositoryInterface::class);
        $this->logger = $objectManager->get(LoggerInterface::class);

        $this->notifyOrdersAreReadyForPickupEmailSender = $objectManager->create(NotifyOrdersAreReadyForPickupEmailSender::class);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventoryInStorePickupApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoDataFixture Magento_InventoryInStorePickupApi::Test/_files/source_addresses.php
     * @magentoDataFixture Magento_InventoryInStorePickupApi::Test/_files/source_pickup_location_attributes.php
     * @magentoDataFixture Magento_InventoryInStorePickupSalesApi::Test/_files/create_in_store_pickup_quote_on_eu_website_guest.php
     * @magentoDataFixture Magento_InventoryInStorePickupSalesApi::Test/_files/place_order.php
     *
     * @magentoConfigFixture store_for_eu_website_store carriers/instore/active 1
     *
     * @magentoDbIsolation disabled
     * @magentoAppIsolation enabled
     * @dataProvider dataProvider
     *
     * @param string $sourceId
     * @param string|null $errorMessage
     * @throws
     */
    public function testExecute(): void
    {
        // Set up configuration values
        $this->config->setValue('sales_email/general/async_sending', true);
        $this->config->setValue('sales_exmail/general/sending_limit', 10);

        // Create mock orders and history items
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('send_notification', 1)
            ->addFilter('notification_sent', 0)
            ->setPageSize(10)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        foreach ($orders as $order) {
            $this->assertTrue($this->emailSender->send($order, true));

            $historySearchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_name', ['order', 'shipment'], 'in')
                ->addFilter('is_customer_notified', 0)
                ->addFilter('comment', 0)
                ->addFilter('parent_id', $order->getEntityId())
                ->create();

            $historyItems = $this->orderStatusHistoryRepository->getList($historySearchCriteria)->getItems();

            foreach ($historyItems as $historyItem) {
                $historyItem->setIsCustomerNotified(1);
                try {
                    $this->orderStatusHistoryRepository->save($historyItem);
                } catch (CouldNotSaveException $e) {
                    $this->logger->error($e->getLogMessage());
                }
            }
        }

        // Execute the method
        $this->notifyOrdersAreReadyForPickupEmailSender->execute();

        // Assertions
        foreach ($orders as $order) {
            $this->assertEquals(1, $order->getSendNotification());
        }
    }
}
