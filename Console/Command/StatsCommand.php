<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatsCommand extends Command
{
    public function __construct(
        private readonly SubscriptionResource $subscriptionResource,
        private readonly DispatchResource $dispatchResource,
        private readonly ProductRepositoryInterface $productRepository,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:stats')
            ->setDescription('Quick health snapshot: counts by status, oldest pending row, dispatch queue, top SKUs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $snapshot = $this->subscriptionResource->getHealthSnapshot();
        $dispatchCounts = $this->dispatchResource->getCountsByStatus();

        $output->writeln('<comment>Subscriptions by status</comment>');
        $subTable = new Table($output);
        $subTable->setHeaders(['status', 'count']);
        foreach ($snapshot['counts_by_status'] as $status => $count) {
            $subTable->addRow([$status, $count]);
        }
        $subTable->render();

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>Oldest pending row:</comment> %s',
            $snapshot['oldest_pending'] ?? '<info>none</info>'
        ));

        $output->writeln('');
        $output->writeln('<comment>Dispatch queue by status</comment>');
        $dispatchTable = new Table($output);
        $dispatchTable->setHeaders(['status', 'count']);
        if ($dispatchCounts === []) {
            $dispatchTable->addRow(['<info>(empty)</info>', '']);
        } else {
            foreach ($dispatchCounts as $status => $count) {
                $dispatchTable->addRow([$status, $count]);
            }
        }
        $dispatchTable->render();

        $output->writeln('');
        $output->writeln('<comment>Top 10 SKUs by pending subscribers</comment>');
        $skuTable = new Table($output);
        $skuTable->setHeaders(['product_id', 'sku', 'name', 'pending']);
        if ($snapshot['top_pending_skus'] === []) {
            $skuTable->addRow(['<info>(none)</info>', '', '', '']);
        } else {
            foreach ($snapshot['top_pending_skus'] as $row) {
                try {
                    $product = $this->productRepository->getById($row['product_id']);
                    $sku = (string) $product->getSku();
                    $name = (string) $product->getName();
                } catch (NoSuchEntityException) {
                    $sku = '<deleted>';
                    $name = '';
                }
                $skuTable->addRow([$row['product_id'], $sku, $name, $row['count']]);
            }
        }
        $skuTable->render();

        return Command::SUCCESS;
    }
}
