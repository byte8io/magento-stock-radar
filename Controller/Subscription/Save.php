<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Subscription;

use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\SubscribedProductTracker;
use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Captcha\Helper\Data as CaptchaHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Save implements HttpPostActionInterface
{
    private const CAPTCHA_FORM_ID = 'byte8_stock_radar_subscribe';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly SubscriptionService $subscriptionService,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly LoggerInterface $logger,
        private readonly ConfigInterface $config,
        private readonly SubscribedProductTracker $subscribedTracker,
        private readonly ?CaptchaHelper $captchaHelper = null
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $sku = trim((string) $this->request->getParam('sku', ''));
        $email = trim((string) $this->request->getParam('email', ''));

        if ($sku === '' || $email === '') {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => __('SKU and email are required.'),
            ]);
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();

            if ($this->config->isCaptchaEnabled($storeId) && !$this->isCaptchaValid()) {
                return $result->setHttpResponseCode(422)->setData([
                    'success' => false,
                    'message' => __('Incorrect CAPTCHA. Please try again.'),
                ]);
            }
            $customerId = $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : null;

            $isLikelyBot = $this->config->isHoneypotEnabled($storeId)
                && trim((string) $this->request->getParam('website', '')) !== '';

            $outcome = $this->subscriptionService->subscribe(
                $sku,
                $email,
                $storeId,
                $customerId,
                (string) $this->request->getClientIp(),
                $isLikelyBot
            );

            if (empty($outcome['silent_drop'])) {
                $this->subscribedTracker->remember($sku, $storeId);
            }

            $requiresConfirmation = !empty($outcome['requires_confirmation']);
            $message = $requiresConfirmation
                ? __('Check your inbox — please click the link in our email to confirm your subscription.')
                : __("We'll email you when this product is back in stock.");

            return $result->setData([
                'success' => true,
                'requires_confirmation' => $requiresConfirmation,
                'message' => $message,
            ]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(422)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Byte8 StockRadar subscribe failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('Something went wrong. Please try again.'),
            ]);
        }
    }

    /**
     * Validates via Magento_Captcha if the helper is wired and the form's
     * captcha is required for the current visitor (which respects core's
     * mode/after_fail logic too). Returns true when CAPTCHA is configured but
     * not required for this request — only an actual mismatch fails.
     */
    private function isCaptchaValid(): bool
    {
        if ($this->captchaHelper === null) {
            // Magento_Captcha not installed — the admin flag is set but we
            // can't validate. Reject conservatively.
            return false;
        }

        $captcha = $this->captchaHelper->getCaptcha(self::CAPTCHA_FORM_ID);
        if (!$captcha->isRequired()) {
            return true;
        }

        $word = (string) ($this->request->getPost('captcha')[self::CAPTCHA_FORM_ID] ?? '');
        if (!$captcha->isCorrect($word)) {
            $captcha->logAttempt(self::CAPTCHA_FORM_ID);
            return false;
        }
        $captcha->logAttempt(self::CAPTCHA_FORM_ID);
        return true;
    }
}
