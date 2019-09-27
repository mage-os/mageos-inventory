<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryInStorePickupApi\Test\Api;

use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryInStorePickupApi\Api\Data\PickupLocationInterface;
use Magento\InventoryInStorePickupApi\Api\Data\SearchRequest\DistanceFilterInterface;
use Magento\InventoryInStorePickupApi\Model\GetPickupLocationInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\TestFramework\Assert\AssertArrayContains;
use Magento\TestFramework\TestCase\WebapiAbstract;

class GetPickupLocationsTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/inventory/in-store-pickup/pickup-locations';
    private const SERVICE_NAME = 'inventoryInStorePickupApiGetPickupLocationsV1';

    /**
     * @var GetPickupLocationInterface
     */
    private $getPickupLocation;

    public function setUp()
    {
        $this->getPickupLocation = ObjectManager::getInstance()->get(GetPickupLocationInterface::class);
    }

    /**
     * Run combined tests with multiple params/filters.
     *
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_addresses.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_pickup_location_attributes.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/inventory_geoname.php
     *
     * @magentoConfigFixture default/cataloginventory/source_selection_distance_based/provider offline
     *
     * @param array $searchRequestData
     * @param string[] $sortedPickupLocationCodes
     * @param int $expectedTotalCount
     *
     * @dataProvider executeCombinedDataProvider
     * @magentoAppArea frontend
     *
     * @magentoDbIsolation disabled
     *
     * @throws NoSuchEntityException
     */
    public function testExecuteCombined(
        array $searchRequestData,
        array $sortedPickupLocationCodes,
        int $expectedTotalCount
    ): void {
        $requestData = [
            'searchRequest' => $searchRequestData
        ];

        $response = $this->sendRequest($requestData);

        $responseLocationCodes = $this->extractLocationCodesFromResponse($response);
        AssertArrayContains::assert($sortedPickupLocationCodes, $responseLocationCodes);

        $this->comparePickupLocations(
            $response['items'],
            $sortedPickupLocationCodes,
            $searchRequestData['scopeCode']
        );

        $this->assertEquals($expectedTotalCount, $response['total_count']);
        $this->assertCount(count($sortedPickupLocationCodes), $response['items']);
    }

    /**
     * [
     *      Search Request Distance Filter[
     *          Country,
     *          Postcode,
     *          Region,
     *          City,
     *          Radius (in KM)
     *      ],
     *      Sales Channel Code,
     *      Sort Orders[
     *          Sort Order[
     *              Direction,
     *              Field
     *      ],
     *      Expected Pickup Location Codes[]
     * ]
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function executeCombinedDataProvider(): array
    {
        return [
            [ /* Data set #0 */
                [
                    'distanceFilter' => [
                        'radius' => 750,
                        'postcode' => '86559',
                        'country' => 'DE'
                    ],
                    'filterSet' => [
                        'cityFilter' => [
                            'value' => 'Kolbermoor,Mitry-Mory',
                            'conditionType' => 'in'
                        ],
                        'regionIdFilter' => [
                            'value' => '259',
                            'conditionType' => 'eq'
                        ],
                        'regionFilter' => [
                            'value' => 'Seine-et-Marne',
                            'conditionType' => 'eq'
                        ],
                    ],
                    'scopeCode' => 'global_website',
                ],
                ['eu-1'],
                1
            ],
            [ /* Data set #1 */
                [
                    'distanceFilter' => [
                        'radius' => 6371000,
                        'postcode' => '86559',
                        'country' => 'DE'
                    ],
                    'filterSet' => [
                        'nameFilter' => [
                            'value' => 'source',
                            'conditionType' => 'fulltext'
                        ],
                        'cityFilter' => [
                            'value' => 'Kolbermoor,Mitry-Mory,Burlingame',
                            'conditionType' => 'in'
                        ],
                        'countryFilter' => [
                            'value' => 'DE',
                            'conditionType' => 'neq'
                        ],
                    ],
                    'scopeCode' => 'global_website',
                    'pageSize' => 2,
                    'currentPage' => 2,
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_DESC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ]
                ],
                ['us-1', 'eu-1'],
                2
            ],
            [ /* Data set #2 */
                [
                    'distanceFilter' => [
                        'radius' => 750,
                        'postcode' => '86559',
                        'country' => 'DE'
                    ],
                    'scopeCode' => 'global_website',
                    'pageSize' => 1,
                    'currentPage' => 1,
                ],
                ['eu-1'],
                2
            ],
        ];
    }

    /**
     * Run tests on distance filter.
     *
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_addresses.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_pickup_location_attributes.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/inventory_geoname.php
     *
     * @magentoConfigFixture default/cataloginventory/source_selection_distance_based/provider offline
     *
     * @param array $searchRequestData
     * @param string[] $sortedPickupLocationCodes
     *
     * @dataProvider executeDistanceFilterOfflineDataProvider
     * @magentoAppArea frontend
     *
     * @magentoDbIsolation disabled
     *
     * @throws NoSuchEntityException
     */
    public function testExecuteDistanceFilterOffline(
        array $searchRequestData,
        array $sortedPickupLocationCodes
    ) : void {
        $requestData = [
            'searchRequest' => $searchRequestData
        ];

        $response = $this->sendRequest($requestData);

        $responseLocationCodes = $this->extractLocationCodesFromResponse($response);
        AssertArrayContains::assert($sortedPickupLocationCodes, $responseLocationCodes);

        $this->comparePickupLocations(
            $response['items'],
            $sortedPickupLocationCodes,
            $searchRequestData['scopeCode']
        );

        $this->assertEquals(count($sortedPickupLocationCodes), $response['total_count']);
        $this->assertCount(count($sortedPickupLocationCodes), $response['items']);
    }

    /**
     * [
     *      Search Request Distance Filter[
     *          Country,
     *          Postcode,
     *          Region,
     *          City,
     *          Radius (in KM)
     *      ],
     *      Sales Channel Code,
     *      Sort Orders[
     *          Sort Order[
     *              Direction,
     *              Field
     *      ],
     *      Expected Pickup Location Codes[]
     * ]
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function executeDistanceFilterOfflineDataProvider(): array
    {
        return [
            [ /* Data set #0 */
                [
                    'distanceFilter' => [
                        'country' => 'DE',
                        'postcode' => '81671',
                        'radius' => 500
                    ],
                    'scopeCode' => 'eu_website',
                ],
                ['eu-3']
            ],
            [ /* Data set #1 */
                [
                    'distanceFilter' => [
                        'country' => 'FR',
                        'region' => 'Bretagne',
                        'radius' => 1000
                    ],
                    'scopeCode' => 'eu_website',
                ],
                ['eu-1']
            ],
            [ /* Data set #2 */
                [
                    'distanceFilter' => [
                        'country' => 'FR',
                        'city' => 'Saint-Saturnin-lès-Apt',
                        'radius' => 1000],
                    'scopeCode' => 'global_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ],
                ],
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #3 */
                [
                    'distanceFilter' => [
                        'country' => 'IT',
                        'postcode' => '12022',
                        'radius' => 350],
                    'scopeCode' => 'eu_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ],
                ],
                []
            ],
            [ /* Data set #4 */
                [
                    'distanceFilter' => [
                        'country' => 'IT',
                        'postcode' => '39030',
                        'region' => 'Trentino-Alto Adige',
                        'city' => 'Rasun Di Sotto',
                        'radius' => 350],
                    'scopeCode' => 'eu_website',
                ],
                ['eu-3']
            ],
            [ /* Data set #5 */
                [
                    'distanceFilter' => [
                        'country' => 'DE',
                        'postcode' => '86559',
                        'radius' => 750],
                    'scopeCode' => 'global_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ],
                ],
                ['eu-3', 'eu-1']
            ],
            [ /* Data set #6 */
                [
                    'distanceFilter' => [
                        'country' => 'US',
                        'region' => 'Kansas',
                        'radius' => 1000],
                    'scopeCode' => 'us_website',
                ],
                ['us-1']
            ],
            [ /* Data set #7. Test with descending distance sort. */
                [
                    'distanceFilter' => [
                        'country' => 'DE',
                        'postcode' => '86559',
                        'radius' => 750],
                    'scopeCode' => 'global_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_DESC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ],
                ],
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #8. Test without distance sort. */
                [
                    'distanceFilter' => [
                        'country' => 'FR',
                        'city' => 'Saint-Saturnin-lès-Apt',
                        'radius' => 1000
                    ],
                    'scopeCode' => 'global_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => SourceInterface::CITY
                        ]
                    ],
                ],
                ['eu-3', 'eu-1']
            ],
            [ /* Data set #9. Test with multiple sorts. Distance must be in priority. */
                [
                    'distanceFilter' => [
                        'country' => 'FR',
                        'city' => 'Saint-Saturnin-lès-Apt',
                        'radius' => 1000],
                    'scopeCode' => 'global_website',
                    'sort' => [
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => SourceInterface::CITY
                        ],
                        [
                            SortOrder::DIRECTION => SortOrder::SORT_ASC,
                            SortOrder::FIELD => DistanceFilterInterface::DISTANCE_FIELD
                        ]
                    ],
                ],
                ['eu-1', 'eu-3']
            ],
        ];
    }

    /**
     * Run tests on filter set.
     *
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_addresses.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_pickup_location_attributes.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/stock_website_sales_channels.php
     *
     * @param array $searchRequestData
     * @param string $salesChannelCode
     * @param string[] $sortedPickupLocationCodes
     *
     * @dataProvider executeFilterSetDataProvider
     * @magentoAppArea frontend
     *
     * @magentoDbIsolation disabled
     *
     * @throws NoSuchEntityException
     */
    public function testExecuteFilterSet(
        array $searchRequestData,
        string $salesChannelCode,
        array $sortedPickupLocationCodes
    ): void {
        $requestData = [
            'searchRequest' => [
                'filterSet' => $searchRequestData,
                'scopeCode' => $salesChannelCode,
            ]
        ];

        $response = $this->sendRequest($requestData);

        $responseLocationCodes = $this->extractLocationCodesFromResponse($response);
        AssertArrayContains::assert($sortedPickupLocationCodes, $responseLocationCodes);

        $this->comparePickupLocations(
            $response['items'],
            $sortedPickupLocationCodes,
            $salesChannelCode
        );

        $this->assertEquals(count($sortedPickupLocationCodes), $response['total_count']);
        $this->assertCount(count($sortedPickupLocationCodes), $response['items']);
    }

    /**
     * [
     *      Filter Set[
     *          Country,
     *          Region,
     *          RegionId,
     *          City,
     *          Postcode,
     *          Street,
     *          Name,
     *          PickupLocationCode
     *      ],
     *      Sales Channel Code,
     *      Expected Pickup Location Codes[]
     * ]
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function executeFilterSetDataProvider(): array
    {
        return [
            [ /* Data set #0 */
                [
                    'countryFilter' => ['value' => 'FR', 'conditionType' => 'eq']
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #1 */
                [
                    'countryFilter' => ['value' => 'DE', 'conditionType' => 'eq']
                ],
                'eu_website',
                ['eu-3']
            ],
            [ /* Data set #2 */
                [
                    'countryFilter' => ['value' => 'DE', 'conditionType' => 'neq']
                ],
                'global_website',
                ['eu-1', 'us-1']
            ],
            [ /* Data set #3 */
                [
                    'countryFilter' => ['value' => 'DE,FR', 'conditionType' => 'in']
                ],
                'global_website',
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #4 */
                [
                    'countryFilter' => ['value' => 'DE', 'conditionType' => 'neq'],
                    'cityFilter' => ['value' => 'Mitry-Mory', 'conditionType' => 'eq']
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #5 */
                [
                    'countryFilter' => ['value' => 'FR', 'conditionType' => 'neq'],
                    'cityFilter' => ['value' => 'Mitry-Mory', 'conditionType' => 'eq']
                ],
                'eu_website',
                []
            ],
            [ /* Data set #6 */
                [
                    'cityFilter' => ['value' => 'Kolbermoor', 'conditionType' => 'eq'],
                ],
                'eu_website',
                ['eu-3']
            ],
            [ /* Data set #7 */
                [
                    'countryFilter' => ['value' => 'DE', 'conditionType' => 'eq'],
                    'cityFilter' => ['value' => 'Mitry-Mory,Kolbermoor', 'conditionType' => 'in'],
                ],
                'eu_website',
                ['eu-3']
            ],
            [ /* Data set #8 */
                [
                    'postcodeFilter' => ['value' => '66413', 'conditionType' => 'eq'],
                ],
                'global_website',
                ['us-1']
            ],
            [ /* Data set #9 */
                [
                    'countryFilter' => ['value' => 'FR', 'conditionType' => 'eq'],
                    'postcodeFilter' => ['value' => '77292 CEDEX', 'conditionType' => 'eq'],
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #10 */
                [
                    'countryFilter' => ['value' => 'FR,DE', 'conditionType' => 'in'],
                    'postcodeFilter' => ['value' => '77292 CEDEX,83059', 'conditionType' => 'in'],
                ],
                'eu_website',
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #11 */
                [
                    'cityFilter' => ['value' => 'Burlingame', 'conditionType' => 'eq'],
                    'postcodeFilter' => ['value' => '66413', 'conditionType' => 'eq'],
                ],
                'global_website',
                ['us-1']
            ],
            [ /* Data set #12 */
                [
                    'cityFilter' => ['value' => 'Burlingame', 'conditionType' => 'eq'],
                    'postcodeFilter' => ['value' => '66413', 'conditionType' => 'eq'],
                ],
                'eu_website',
                []
            ],
            [ /* Data set #13 */
                [
                    'streetFilter' => ['value' => 'Bloomquist Dr 100', 'conditionType' => 'eq'],
                ],
                'global_website',
                ['us-1']
            ],
            [ /* Data set #14 */
                [
                    'cityFilter' => ['value' => 'Mitry-Mory', 'conditionType' => 'eq'],
                    'streetFilter' => ['value' => 'Rue Paul Vaillant Couturier 31', 'conditionType' => 'eq'],
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #15 */
                [
                    'streetFilter' => ['value' => 'Rosenheimer%', 'conditionType' => 'like'],
                ],
                'eu_website',
                ['eu-3']
            ],
            [ /* Data set #16 */
                [
                    'postcodeFilter' => ['value' => '77292 CEDEX', 'conditionType' => 'eq'],
                    'streetFilter' => ['value' => 'Rue Paul%', 'conditionType' => 'like'],
                ],
                'global_website',
                ['eu-1']
            ],
            [ /* Data set #17 */
                [
                    'countryFilter' => ['value' => 'US', 'conditionType' => 'neq'],
                    'cityFilter' => ['value' => 'Mitry-Mory', 'conditionType' => 'eq'],
                    'postcodeFilter' => ['value' => '77%', 'conditionType' => 'like'],
                    'streetFilter' => ['value' => 'Rue Paul%', 'conditionType' => 'like'],
                ],
                'global_website',
                ['eu-1']
            ],
            [ /* Data set #18 */
                [
                    'regionIdFilter' => ['value' => '81', 'conditionType' => 'eq'],
                ],
                'eu_website',
                ['eu-3']
            ],
            [ /* Data set #19 */
                [
                    'regionFilter' => ['value' => 'Seine-et-Marne', 'conditionType' => 'eq'],
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #20 */
                [
                    'regionFilter' => ['value' => 'California', 'conditionType' => 'eq'],
                    'regionIdFilter' => ['value' => '12', 'conditionType' => 'eq'],
                ],
                'global_website',
                ['us-1']
            ],
            [ /* Data set #21 */
                [
                    'regionFilter' => ['value' => 'California', 'conditionType' => 'eq'],
                    'regionIdFilter' => ['value' => '94', 'conditionType' => 'eq'],
                ],
                'global_website',
                []
            ],
            [ /* Data set #22 */
                [
                    'countryFilter' => ['value' => 'FR', 'conditionType' => 'neq'],
                    'regionFilter' => ['value' => 'Bayern', 'conditionType' => 'eq'],
                    'regionIdFilter' => ['value' => '81', 'conditionType' => 'eq'],
                    'cityFilter' => ['value' => 'K%', 'conditionType' => 'like'],
                    'postcodeFilter' => ['value' => '83059,13100', 'conditionType' => 'in'],
                    'streetFilter' => ['value' => 'heimer', 'conditionType' => 'fulltext'],
                ],
                'global_website',
                ['eu-3']
            ],
            [ /* Data set #23 */
                [
                    'pickupLocationCodeFilter' => ['value' => 'eu-1', 'conditionType' => 'eq']
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #24 */
                [
                    'pickupLocationCodeFilter' => ['value' => 'eu-1,eu-3', 'conditionType' => 'in']
                ],
                'eu_website',
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #25 */
                [
                    'pickupLocationCodeFilter' => ['value' => 'eu%', 'conditionType' => 'like']
                ],
                'global_website',
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #26 */
                [
                    'pickupLocationCodeFilter' => ['value' => 'u', 'conditionType' => 'fulltext']
                ],
                'global_website',
                ['eu-1', 'eu-3', 'us-1']
            ],
            [ /* Data set #27 */
                [
                    'pickupLocationCodeFilter' => ['value' => 'eu-2', 'conditionType' => 'eq']
                ],
                'eu_website',
                []
            ],
            [ /* Data set #28 */
                [
                    'nameFilter' => ['value' => 'EU-source-1', 'conditionType' => 'eq']
                ],
                'eu_website',
                ['eu-1']
            ],
            [ /* Data set #29 */
                [
                    'nameFilter' => ['value' => 'source', 'conditionType' => 'fulltext']
                ],
                'global_website',
                ['eu-1', 'eu-3', 'us-1']
            ],
            [ /* Data set #30 */
                [
                    'nameFilter' => ['value' => 'source', 'conditionType' => 'fulltext'],
                    'pickupLocationCodeFilter' => ['value' => 'eu%', 'conditionType' => 'like']
                ],
                'global_website',
                ['eu-1', 'eu-3']
            ],
            [ /* Data set #31 */
                [
                    'countryFilter' => ['value' => 'FR', 'conditionType' => 'neq'],
                    'regionFilter' => ['value' => 'Bayern', 'conditionType' => 'eq'],
                    'regionIdFilter' => ['value' => '81', 'conditionType' => 'eq'],
                    'cityFilter' => ['value' => 'K%', 'conditionType' => 'like'],
                    'postcodeFilter' => ['value' => '83059,13100', 'conditionType' => 'in'],
                    'streetFilter' => ['value' => 'heimer', 'conditionType' => 'fulltext'],
                    'nameFilter' => ['value' => 'source', 'conditionType' => 'fulltext'],
                    'pickupLocationCodeFilter' => ['value' => 'eu%', 'conditionType' => 'like']
                ],
                'global_website',
                ['eu-3']
            ],
        ];
    }

    /**
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/sources.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_addresses.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryInStorePickup/Test/_files/source_pickup_location_attributes.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stocks.php
     * @magentoDataFixture ../../../../app/code/Magento/InventoryApi/Test/_files/stock_source_links.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/websites_with_stores.php
     * @magentoDataFixture ../../../../app/code/Magento/InventorySalesApi/Test/_files/stock_website_sales_channels.php
     *
     * @param string $salesChannelCode
     * @param array $paging
     * @param int $expectedTotalCount
     * @param array $sortOrder
     * @param string[] $sortedPickupLocationCodes
     *
     * @dataProvider executeGeneralDataProvider
     * @magentoAppArea frontend
     *
     * @magentoDbIsolation disabled
     *
     * @throws NoSuchEntityException
     */
    public function testExecuteGeneral(
        string $salesChannelCode,
        array $paging,
        int $expectedTotalCount,
        array $sortOrder,
        array $sortedPickupLocationCodes
    ): void {
        $requestData = [
            'searchRequest' => [
                'scopeCode' => $salesChannelCode,
                'sort' => $sortOrder,
                'pageSize' => current($paging),
                'currentPage' => next($paging)
            ]
        ];

        $response = $this->sendRequest($requestData);

        $responseLocationCodes = $this->extractLocationCodesFromResponse($response);
        AssertArrayContains::assert($sortedPickupLocationCodes, $responseLocationCodes);

        $this->comparePickupLocations(
            $response['items'],
            $sortedPickupLocationCodes,
            $salesChannelCode
        );

        self::assertEquals($expectedTotalCount, $response['total_count']);
    }

    /**
     * [
     *      Sales Channel Code,
     *      Page[
     *          Page Size,
     *          Current Page
     *      ],
     *      Expected Total Count,
     *      Sort Orders[
     *          Sort Order[
     *              Direction,
     *              Field
     *      ],
     *      Expected Pickup Location Codes[]
     * ]
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function executeGeneralDataProvider(): array
    {
        return [
            [ /* Data set #0 */
                'global_website',
                [],
                3,
                [],
                ['eu-1', 'eu-3', 'us-1']
            ],
            [ /* Data set #1 */
                'global_website',
                [],
                3,
                [],
                ['eu-1', 'eu-3', 'us-1']
            ],
            [ /* Data set #2 */
                'global_website',
                [1, 1],
                3,
                [],
                ['eu-1']
            ],
            [ /* Data set #3 */
                'global_website',
                [1, 2],
                3,
                [],
                ['eu-3']
            ],
            [ /* Data set #4 */
                'global_website',
                [],
                3,
                [
                    [
                        SortOrder::DIRECTION => SortOrder::SORT_DESC,
                        SortOrder::FIELD => SourceInterface::COUNTRY_ID
                    ]
                ],
                ['us-1', 'eu-1', 'eu-3']
            ],
            [ /* Data set #5 */
                'global_website',
                [],
                3,
                [
                    [
                        SortOrder::DIRECTION => SortOrder::SORT_DESC,
                        SortOrder::FIELD => SourceInterface::POSTCODE
                    ],
                    [
                        SortOrder::DIRECTION => SortOrder::SORT_ASC,
                        SortOrder::FIELD => SourceInterface::COUNTRY_ID
                    ]
                ],
                ['eu-3', 'eu-1', 'us-1']
            ],
            [ /* Data set #6 */
                'global_website',
                [1, 2],
                3,
                [
                    [
                        SortOrder::DIRECTION => SortOrder::SORT_DESC,
                        SortOrder::FIELD => SourceInterface::COUNTRY_ID
                    ]
                ],
                ['eu-1']
            ],
        ];
    }

    /**
     * @param array $requestData
     * @return array|bool|float|int|string
     */
    private function sendRequest(array $requestData)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . '?' . http_build_query($requestData),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'operation' => self::SERVICE_NAME . 'Execute',
            ],
        ];

        $response = (TESTS_WEB_API_ADAPTER == self::ADAPTER_REST)
            ? $this->_webApiCall($serviceInfo)
            : $this->_webApiCall($serviceInfo, $requestData);
        return $response;
    }

    /**
     * @param $response
     * @return array
     */
    private function extractLocationCodesFromResponse($response): array
    {
        $responseLocationCodes = [];
        foreach ($response['items'] as $item) {
            $responseLocationCodes[] = $item['pickup_location_code'];
        }
        return $responseLocationCodes;
    }

    /**
     * Compare received Pickup Locations.
     *
     * @param array $responseItems
     * @param array $expected
     * @param string $scopeCode
     *
     * @throws NoSuchEntityException
     */
    private function comparePickupLocations(array $responseItems, array $expected, string $scopeCode)
    {
        $index = 0;
        foreach ($responseItems as $item) {
            $pickupLocation = $this->getPickupLocation->execute(
                $expected[$index],
                SalesChannelInterface::TYPE_WEBSITE,
                $scopeCode
            );
            $this->compareFields($pickupLocation, $item);
            $index++;
        }
    }

    /**
     * Compare if received Pickup Location data match to the original entity.
     *
     * @param PickupLocationInterface $pickupLocation
     * @param array $data
     */
    private function compareFields(PickupLocationInterface $pickupLocation, array $data): void
    {
        $this->assertEquals($pickupLocation->getPickupLocationCode(), $data['pickup_location_code']);
        $this->assertEquals($pickupLocation->getName(), $data['name'] ?? null);
        $this->assertEquals($pickupLocation->getEmail(), $data['email'] ?? null);
        $this->assertEquals($pickupLocation->getFax(), $data['fax'] ?? null);
        $this->assertEquals($pickupLocation->getDescription(), $data['description'] ?? null);
        $this->assertEquals($pickupLocation->getLatitude(), $data['latitude'] ?? null);
        $this->assertEquals($pickupLocation->getLongitude(), $data['longitude'] ?? null);
        $this->assertEquals($pickupLocation->getCountryId(), $data['country_id'] ?? null);
        $this->assertEquals($pickupLocation->getRegionId(), $data['region_id'] ?? null);
        $this->assertEquals($pickupLocation->getRegion(), $data['region'] ?? null);
        $this->assertEquals($pickupLocation->getCity(), $data['city'] ?? null);
        $this->assertEquals($pickupLocation->getStreet(), $data['street'] ?? null);
        $this->assertEquals($pickupLocation->getPostcode(), $data['postcode'] ?? null);
        $this->assertEquals($pickupLocation->getPhone(), $data['phone'] ?? null);
    }
}