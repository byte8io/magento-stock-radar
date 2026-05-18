<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\StockRadar\Cron\DispatchSender;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drains the dispatch queue immediately rather than waiting for the next
 * 1-minute cron tick. Wraps the cron job directly so the behaviour
 * matches exactly. `--force` ignores the per-row scheduled_at so queued
 * rows still inside their throttle window are sent now anyway.
 */
class DispatchRunCommand extends Command
{
    public function __construct(
        private readonly DispatchSender $dispatchSender,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stock-radar:dispatch:run')
            ->setDescription('Drain the dispatch queue now (same logic as the byte8_stock_radar_dispatch cron job).')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Ignore scheduled_at — send every queued row immediately, regardless of throttle window.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = (bool) $input->getOption('force');

        $output->writeln($force
            ? '<info>Draining dispatch queue (force — ignoring scheduled_at)...</info>'
            : '<info>Draining dispatch queue (due rows only)...</info>'
        );

        $result = $this->dispatchSender->drain($force);

        $output->writeln(sprintf(
            '<info>Fetched %d row(s) — sent %d, failed %d.</info>',
            $result['fetched'],
            $result['sent'],
            $result['failed']
        ));

        if ($result['fetched'] === 0 && !$force) {
            $output->writeln(
                '<comment>If `stats` shows queued rows but nothing was fetched, '
                . 'they are still inside the throttle window. Re-run with --force or wait.</comment>'
            );
        }

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
