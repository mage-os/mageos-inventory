<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Console\Command;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOversoldSourceItems;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report source/SKU positions whose physical stock is below the reservations
 * committed against that source. Read-only; exits non-zero when any oversold
 * position is found so it can gate monitoring.
 */
class DetectOversellCommand extends Command
{
    private const OPTION_SOURCE = 'source';
    private const OPTION_SKU = 'sku';
    private const OPTION_LIMIT = 'limit';
    private const DEFAULT_LIMIT = 1000;

    /**
     * @param GetOversoldSourceItems $getOversoldSourceItems
     * @param string|null $name
     */
    public function __construct(
        private readonly GetOversoldSourceItems $getOversoldSourceItems,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('inventory:reservation:detect-oversell');
        $this->setDescription('Report source stock positions that are below their committed reservations.');
        $this->addOption(self::OPTION_SOURCE, null, InputOption::VALUE_REQUIRED, 'Filter by source code.');
        $this->addOption(self::OPTION_SKU, null, InputOption::VALUE_REQUIRED, 'Filter by SKU.');
        $this->addOption(
            self::OPTION_LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'Maximum number of oversold positions to scan.',
            (string)self::DEFAULT_LIMIT
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int)$input->getOption(self::OPTION_LIMIT));
        $source = $input->getOption(self::OPTION_SOURCE);
        $sku = $input->getOption(self::OPTION_SKU);

        $rows = $this->getOversoldSourceItems->execute($limit);
        $rows = array_values(array_filter($rows, static function (array $row) use ($source, $sku) {
            return ($source === null || $row['source_code'] === $source)
                && ($sku === null || $row['sku'] === $sku);
        }));

        if (empty($rows)) {
            $output->writeln('<info>No oversold source positions found.</info>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Source', 'SKU', 'Physical', 'Committed', 'Oversold by']);
        foreach ($rows as $row) {
            $table->addRow([
                $row['source_code'],
                $row['sku'],
                $row['physical'],
                -$row['reserved'],
                -$row['delta'],
            ]);
        }
        $table->render();
        $output->writeln(sprintf('<comment>%d oversold source position(s).</comment>', count($rows)));

        return Command::FAILURE;
    }
}
