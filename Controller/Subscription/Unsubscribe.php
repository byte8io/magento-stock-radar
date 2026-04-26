<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Subscription;

use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;

class Unsubscribe implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly SubscriptionService $subscriptionService,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $token = (string) $this->request->getParam('token', '');
        $this->subscriptionService->unsubscribeByToken($token);

        // Same message regardless of token validity — don't leak which tokens
        // exist
        $this->messageManager->addSuccessMessage(__('You have been unsubscribed.'));

        return $this->redirectFactory->create()->setPath('/');
    }
}
