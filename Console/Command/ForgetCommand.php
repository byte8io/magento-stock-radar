<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Model\SubscriptionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForgetCommand extends Command
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:forget')
            ->setDescription('GDPR right-to-be-forgotten — delete every stock-radar subscription for an email.')
            ->addArgument('email', InputArgument::REQUIRED, 'Subscriber email to forget')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the count that would be deleted, do not delete.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $count = $this->subscriptionService->countByEmail($email);

        if ($count === 0) {
            $output->writeln(sprintf('<comment>No subscriptions found for %s.</comment>', $email));
            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(sprintf('<info>Would delete %d row(s) for %s. Run again without --dry-run to apply.</info>', $count, $email));
            return Command::SUCCESS;
        }

        $deleted = $this->subscriptionService->forgetByEmail($email);
        $output->writeln(sprintf('<info>Deleted %d row(s) for %s.</info>', $deleted, $email));
        return Command::SUCCESS;
    }
}
