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

/**
 * Double opt-in confirmation landing. Flips an UNCONFIRMED row to PENDING
 * so the stock observer will dispatch a notification when stock returns.
 * Mirrors Unsubscribe's behaviour around token leakage — same message
 * either way, no enumeration.
 */
class Confirm implements HttpGetActionInterface
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
        $this->subscriptionService->confirmByToken($token);

        $this->messageManager->addSuccessMessage(
            __('Your subscription is confirmed. We will email you when this product is back in stock.')
        );

        return $this->redirectFactory->create()->setPath('/');
    }
}
