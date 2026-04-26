<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Subscription;

use Byte8\StockRadar\Model\SubscriptionService;
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
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly SubscriptionService $subscriptionService,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly LoggerInterface $logger
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
            $customerId = $this->customerSession->isLoggedIn()
                ? (int) $this->customerSession->getCustomerId()
                : null;

            $this->subscriptionService->subscribe($sku, $email, $storeId, $customerId);

            return $result->setData([
                'success' => true,
                'message' => __("We'll email you when this product is back in stock."),
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
}
