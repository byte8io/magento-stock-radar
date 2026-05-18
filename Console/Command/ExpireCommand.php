<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Cron\ExpireSubscriptions;
use Byte8\StockRadar\Model\ConfigInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireCommand extends Command
{
    public function __construct(
        private readonly ExpireSubscriptions $expire,
        private readonly ConfigInterface $config,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:expire')
            ->setDescription('Cancel pending subscriptions older than the configured expiry. Same logic as the nightly cron.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $this->config->getSubscriptionExpiryDays();
        if ($days <= 0) {
            $output->writeln('<comment>Expiry is disabled (expiry_days = 0). Nothing to do.</comment>');
            return Command::SUCCESS;
        }

        $cancelled = $this->expire->execute();
        $output->writeln(sprintf(
            '<info>Cancelled %d pending subscription(s) older than %d day(s).</info>',
            $cancelled,
            $days
        ));
        return Command::SUCCESS;
    }
}
