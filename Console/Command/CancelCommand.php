<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Model\EmailHasher;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CancelCommand extends Command
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EmailHasher $emailHasher,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:cancel')
            ->setDescription('Bulk-cancel subscriptions by email, SKU, store, status, or any combination.')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Subscriber email')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Product SKU')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store ID')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by current status (pending/unconfirmed/notified)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show match count without cancelling.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email');
        $sku = $input->getOption('sku');
        $storeId = $input->getOption('store');
        $status = $input->getOption('status');

        if ($email === null && $sku === null && $storeId === null && $status === null) {
            $output->writeln('<error>Provide at least one filter: --email, --sku, --store, or --status.</error>');
            return Command::FAILURE;
        }

        $emailHash = $email !== null ? $this->emailHasher->hash((string) $email) : null;

        $productId = null;
        if ($sku !== null) {
            try {
                $productId = (int) $this->productRepository->get((string) $sku)->getId();
            } catch (NoSuchEntityException) {
                $output->writeln(sprintf('<error>Unknown SKU: %s</error>', $sku));
                return Command::FAILURE;
            }
        }

        $ids = $this->subscriptionResource->findIdsByCriteria(
            $emailHash,
            $productId,
            $storeId !== null ? (int) $storeId : null,
            $status !== null ? (string) $status : null
        );

        if ($ids === []) {
            $output->writeln('<comment>No subscriptions matched.</comment>');
            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('<info>Would cancel %d subscription(s). Re-run without --dry-run.</info>', count($ids)));
            return Command::SUCCESS;
        }

        $changed = $this->subscriptionService->cancelByIds($ids);
        $skipped = count($ids) - $changed;
        $output->writeln(sprintf(
            '<info>Cancelled %d subscription(s)%s.</info>',
            $changed,
            $skipped > 0 ? sprintf(' (%d already terminal, skipped)', $skipped) : ''
        ));
        return Command::SUCCESS;
    }
}
