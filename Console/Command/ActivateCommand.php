<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Console\Command;

use Byte8\Activation\Model\Status;
use Byte8\StockRadar\Model\Activation\Activation;
use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ActivateCommand extends Command
{
    private const ARG_KEY = 'key';
    private const OPT_SKIP_VERIFY = 'skip-verify';

    public function __construct(
        private readonly ConfigResource $configResource,
        private readonly EncryptorInterface $encryptor,
        private readonly ReinitableConfigInterface $reinitableConfig,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Activation $activation,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('byte8:stockradar:activate')
            ->setDescription('Save and verify a Byte8 Stock Radar activation key.')
            ->addArgument(
                self::ARG_KEY,
                InputArgument::REQUIRED,
                'Activation key from byte8.io/activate/stock-radar (format: b8a_stk_...).'
            )
            ->addOption(
                self::OPT_SKIP_VERIFY,
                null,
                InputOption::VALUE_NONE,
                'Save the key without contacting api.byte8.io. Useful in air-gapped environments.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getArgument(self::ARG_KEY));
        if ($key === '') {
            $output->writeln('<error>Activation key must not be empty.</error>');
            return Command::INVALID;
        }

        $encrypted = $this->encryptor->encrypt($key);
        $this->configResource->saveConfig($this->activation->getKeyConfigPath(), $encrypted, ScopeConfigInterface::SCOPE_DEFAULT, 0);
        $this->reinitableConfig->reinit();
        $this->cacheTypeList->cleanType('config');
        $output->writeln('<info>Activation key saved.</info>');

        if ((bool) $input->getOption(self::OPT_SKIP_VERIFY)) {
            $output->writeln('Skipping verify (--skip-verify supplied).');
            return Command::SUCCESS;
        }

        $envelope = $this->activation->refresh($key);
        if ($envelope === null) {
            $output->writeln('<comment>Could not reach api.byte8.io. The key has been saved; the gate will retry on the next subscribe or stock-back event.</comment>');
            return Command::SUCCESS;
        }

        if (($envelope['ok'] ?? false) === true) {
            $output->writeln('<info>Activation verified successfully.</info>');
            $output->writeln(sprintf('  product: %s', $envelope['productId'] ?? 'stock_radar'));
            if (!empty($envelope['expiresAt'])) {
                $output->writeln(sprintf('  expires_at (verify cache): %s', date('c', (int) $envelope['expiresAt'])));
            }
            return Command::SUCCESS;
        }

        $reason = $envelope['error'] ?? 'unknown';
        $output->writeln(sprintf('<error>Activation rejected by server: %s</error>', $reason));
        $status = $this->activation->getStatus();
        if ($status->getState() === Status::STATE_ACTIVE) {
            $output->writeln('<comment>Module is running under the soft-gate window. Key will need to be valid once that ends.</comment>');
        }
        return Command::FAILURE;
    }
}
