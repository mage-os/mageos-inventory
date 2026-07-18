<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\Notification\NotifierInterface;
use Magento\InventorySales\Model\SourceReservation\OversellNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OversellNotifierTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var NotifierInterface|MockObject
     */
    private $notifier;

    /**
     * @var OversellNotifier
     */
    private $oversellNotifier;

    /**
     * @var int
     */
    private $warnings;

    protected function setUp(): void
    {
        $this->warnings = 0;
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('warning')->willReturnCallback(function () {
            $this->warnings++;
        });
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->oversellNotifier = new OversellNotifier($this->logger, $this->notifier);
    }

    public function testLogsEachPositionAndPushesOneAdminNotice(): void
    {
        $this->notifier->expects(self::once())->method('addMajor');

        $this->oversellNotifier->notify([
            $this->item('src-a', 'sku-1', 1.0, -4.0),
            $this->item('src-b', 'sku-2', 0.0, -2.0),
        ]);

        self::assertSame(2, $this->warnings);
    }

    public function testEmptyDoesNothing(): void
    {
        $this->notifier->expects(self::never())->method('addMajor');

        $this->oversellNotifier->notify([]);

        self::assertSame(0, $this->warnings);
    }

    public function testSwallowsAdminNoticeFailure(): void
    {
        $this->notifier->method('addMajor')->willThrowException(new \RuntimeException('inbox down'));

        $this->oversellNotifier->notify([$this->item('src-a', 'sku-1', 1.0, -4.0)]);

        self::assertSame(2, $this->warnings);
    }

    /**
     * @param string $source
     * @param string $sku
     * @param float $physical
     * @param float $reserved
     * @return array<string, mixed>
     */
    private function item(string $source, string $sku, float $physical, float $reserved): array
    {
        return [
            'source_code' => $source,
            'sku' => $sku,
            'physical' => $physical,
            'reserved' => $reserved,
            'delta' => $physical + $reserved,
        ];
    }
}
