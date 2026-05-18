<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manually enqueue dispatch rows for a SKU's pending subscribers as if the
 * stock-save observer had fired. Use case: stock returned via a channel that
 * doesn't write to Magento's catalog inventory (ERP push, manual adjustment
 * with the observer disabled, integration test).
 */
class NotifyCommand extends Command
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly DispatchResource $dispatchResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ConfigInterface $config,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:notify')
            ->setDescription('Enqueue dispatch rows for all pending subscribers of a SKU (manual restock trigger).')
            ->addArgument('sku', InputArgument::OPTIONAL, 'Product SKU (simple SKU for configurable variants). Alternative: --sku')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Product SKU. Same as the positional argument.')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Limit to a single store ID')
            ->addOption('immediate', null, InputOption::VALUE_NONE, 'Bypass the throttle window — schedule every dispatch row for now (use for testing or urgent restocks).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List subscribers without enqueueing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Accept --sku <value> or positional <sku>. --sku wins when both given,
        // so consistent with the `cancel` command's --sku option shape.
        $sku = trim((string) ($input->getOption('sku') ?? $input->getArgument('sku') ?? ''));
        if ($sku === '') {
            $output->writeln('<error>Provide a SKU as the positional argument or via --sku.</error>');
            return Command::FAILURE;
        }
        $storeFilter = $input->getOption('store') !== null ? (int) $input->getOption('store') : null;
        $dryRun = (bool) $input->getOption('dry-run');
        $immediate = (bool) $input->getOption('immediate');

        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException) {
            $output->writeln(sprintf('<error>Unknown SKU: %s</error>', $sku));
            return Command::FAILURE;
        }
        $productId = (int) $product->getId();

        $total = 0;
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if ($storeFilter !== null && $storeFilter !== $storeId) {
                continue;
            }
            if (!$this->config->isActive($storeId)) {
                continue;
            }

            $pending = $this->subscriptionResource->getPendingIdsForProduct($productId, $storeId);
            $count = count($pending);
            if ($count === 0) {
                continue;
            }

            $output->writeln(sprintf(' Store %d: %d pending subscriber(s)', $storeId, $count));

            if (!$dryRun) {
                // --immediate bypasses the throttle window so the row is due
                // right now. Use case: testing, or an urgent restock where
                // staggering the email blast is undesirable.
                $window = $immediate ? 0 : $this->config->getThrottleWindowMinutes($storeId);
                $this->dispatchResource->enqueueBatch($pending, $window);
            }

            $total += $count;
        }

        if ($total === 0) {
            $output->writeln('<comment>No pending subscribers for that SKU.</comment>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<info>Would enqueue %d dispatch row(s). Run again without --dry-run.</info>', $total));
        } elseif ($immediate) {
            $output->writeln(sprintf(
                '<info>Enqueued %d dispatch row(s) with immediate schedule. Run `byte8:stock-radar:dispatch:run` to send now (or wait for the cron tick).</info>',
                $total
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Enqueued %d dispatch row(s). Cron will drain them within the throttle window (%d min). Pass --immediate to bypass.</info>',
                $total,
                $this->config->getThrottleWindowMinutes($storeFilter)
            ));
        }
        return Command::SUCCESS;
    }
}
