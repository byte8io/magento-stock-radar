<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Byte8\StockRadar\Model\Stock\StockChecker;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;
use Psr\Log\LoggerInterface;

/**
 * Single entry point for subscribing/unsubscribing. Used by both the storefront
 * controller and the GraphQL resolvers so validation and side-effects stay in
 * one place.
 */
class SubscriptionService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockChecker $stockChecker,
        private readonly EmailHasher $emailHasher,
        private readonly UnsubscribeTokenGenerator $tokenGenerator,
        private readonly EmailAddress $emailValidator,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RateLimiter $rateLimiter,
        private readonly ConfirmationSender $confirmationSender,
        private readonly LoggerInterface $logger,
        private readonly AdminAlertNotifier $adminAlertNotifier
    ) {
    }

    /**
     * Subscribe an email to a product. Idempotent — repeat calls return the
     * existing subscription rather than failing.
     *
     * `$isLikelyBot=true` (honeypot tripped) silently drops the request: we
     * return the same shape as a successful subscribe so the bot can't tell
     * its submission was rejected, but no DB row is created.
     *
     * @return array{created: bool, silent_drop?: bool}
     */
    public function subscribe(
        string $sku,
        string $email,
        int $storeId,
        ?int $customerId = null,
        ?string $ipAddress = null,
        bool $isLikelyBot = false
    ): array {
        $email = trim($email);

        if ($isLikelyBot) {
            return ['created' => false, 'silent_drop' => true];
        }

        if (!$this->emailValidator->isValid($email)) {
            throw new LocalizedException(__('Please provide a valid email address.'));
        }

        if (!$this->config->isActive($storeId)) {
            throw new LocalizedException(__('Stock notifications are not enabled for this store.'));
        }

        $this->rateLimiter->assertWithinLimits($email, $ipAddress, $storeId);

        $product = $this->productRepository->get($sku, false, $storeId);
        $productId = (int) $product->getId();

        // Only allow subscribing to currently OOS products — otherwise there's
        // nothing to wait for. Configurable parents may show in-stock while
        // their child is OOS, so when we get a configurable here we let it
        // through; the per-variant case is handled by the caller passing the
        // simple SKU directly.
        if ($product->getTypeId() === 'simple') {
            if ($this->stockChecker->isInStock((string) $product->getSku(), $storeId)) {
                throw new LocalizedException(__('This product is currently in stock.'));
            }
        }

        $parentProductId = $product->getTypeId() === 'simple'
            ? $this->resolveParentProductId($productId)
            : null;

        $doubleOptin = $this->config->isDoubleOptinEnabled($storeId);
        $initialStatus = $doubleOptin
            ? SubscriptionInterface::STATUS_UNCONFIRMED
            : SubscriptionInterface::STATUS_PENDING;
        $confirmationToken = $doubleOptin ? $this->tokenGenerator->generate() : null;

        $created = $this->subscriptionResource->upsertPending(
            $productId,
            $parentProductId,
            $storeId,
            $customerId,
            $email,
            $this->emailHasher->hash($email),
            $this->tokenGenerator->generate(),
            $initialStatus,
            $confirmationToken
        );

        if ($doubleOptin && $confirmationToken !== null) {
            try {
                $this->confirmationSender->send($product, $email, $confirmationToken, $storeId);
            } catch (\Throwable $e) {
                // Don't fail the subscribe call if email send fails — the row
                // still exists and an admin can resend manually. Log and
                // continue so the user gets a "check your inbox" message.
                $this->logger->error(
                    'Byte8 StockRadar confirmation email failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        // Admin alert: only consider freshly-created PENDING rows. Unconfirmed
        // rows don't count toward demand because they may never confirm.
        if ($created && $initialStatus === SubscriptionInterface::STATUS_PENDING) {
            try {
                $newCount = $this->subscriptionResource->countPendingForProduct($productId, $storeId);
                $this->adminAlertNotifier->maybeNotifyThresholdCrossed($product, $storeId, $newCount);
            } catch (\Throwable $e) {
                // Never let an alert post failure break the subscribe path.
                $this->logger->warning(
                    'Byte8 StockRadar admin alert failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        return ['created' => $created, 'requires_confirmation' => $doubleOptin];
    }

    /**
     * Double opt-in: flip UNCONFIRMED → PENDING when the token matches.
     * Returns true if a row was actually confirmed. Caller swallows the
     * return value so token validity isn't leaked back to the visitor.
     */
    public function confirmByToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $row = $this->subscriptionResource->findByConfirmationToken($token);
        if ($row === null) {
            return false;
        }

        $subscriptionId = (int) $row[SubscriptionInterface::ENTITY_ID];
        $affected = $this->subscriptionResource->confirmSubscription($subscriptionId);
        if ($affected === 0) {
            return false;
        }

        // Confirmation bumps the pending count for this product+store, so
        // re-check the admin alert threshold from here too — otherwise rows
        // that only become pending after opt-in would slip past the alert.
        try {
            $storeId = (int) $row[SubscriptionInterface::STORE_ID];
            $productId = (int) $this->subscriptionResource->getConnection()->fetchOne(
                $this->subscriptionResource->getConnection()->select()
                    ->from($this->subscriptionResource->getMainTable(), [SubscriptionInterface::PRODUCT_ID])
                    ->where(SubscriptionInterface::ENTITY_ID . ' = ?', $subscriptionId)
            );
            if ($productId > 0) {
                $product = $this->productRepository->getById($productId, false, $storeId);
                $newCount = $this->subscriptionResource->countPendingForProduct($productId, $storeId);
                $this->adminAlertNotifier->maybeNotifyThresholdCrossed($product, $storeId, $newCount);
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Byte8 StockRadar admin alert after confirm failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return true;
    }

    /**
     * Unsubscribe by signed token. Returns true if a row was actually flipped,
     * false if the token didn't match (we don't reveal which to the caller —
     * surface the same success message either way).
     *
     * Accepts pending, notified, and unconfirmed rows. Notified rows flipping
     * to cancelled is cosmetic (they've already been emailed), but it keeps
     * the account UI honest — Cancel on a Notified row used to silently do
     * nothing. Bounced rows stay terminal.
     */
    public function unsubscribeByToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $connection = $this->subscriptionResource->getConnection();
        $rows = (int) $connection->update(
            $this->subscriptionResource->getMainTable(),
            [SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_CANCELLED],
            [
                SubscriptionInterface::UNSUBSCRIBE_TOKEN . ' = ?' => $token,
                SubscriptionInterface::STATUS . ' IN (?)' => [
                    SubscriptionInterface::STATUS_PENDING,
                    SubscriptionInterface::STATUS_NOTIFIED,
                    SubscriptionInterface::STATUS_UNCONFIRMED,
                ],
            ]
        );

        return $rows > 0;
    }

    /**
     * Bulk-cancel for admin row + mass actions. Returns the number of rows
     * that were actually changed (rows already cancelled/bounced are skipped).
     *
     * @param int[] $ids
     */
    public function cancelByIds(array $ids): int
    {
        return $this->subscriptionResource->cancelByIds($ids);
    }

    /**
     * GDPR right-to-be-forgotten. Looks up by SHA-256 of the lowercased
     * email and hard-deletes matching rows (dispatch cascades). Returns
     * the count for the calling CLI / service to surface.
     */
    public function forgetByEmail(string $email): int
    {
        $email = trim($email);
        if ($email === '') {
            return 0;
        }
        return $this->subscriptionResource->deleteByEmailHash($this->emailHasher->hash($email));
    }

    /**
     * Count of subscriptions for an email — preview before forgetByEmail()
     * for `--dry-run` UX.
     */
    public function countByEmail(string $email): int
    {
        $email = trim($email);
        if ($email === '') {
            return 0;
        }
        return $this->subscriptionResource->countByEmailHash($this->emailHasher->hash($email));
    }

    /**
     * Returns null if the simple has no parent configurable. Uses
     * configurable_product_link table directly to avoid loading parent models.
     */
    private function resolveParentProductId(int $simpleProductId): ?int
    {
        $connection = $this->subscriptionResource->getConnection();
        $select = $connection->select()
            ->from(
                $connection->getTableName('catalog_product_super_link'),
                ['parent_id']
            )
            ->where('product_id = ?', $simpleProductId)
            ->limit(1);

        $parentId = $connection->fetchOne($select);
        return $parentId ? (int) $parentId : null;
    }
}
