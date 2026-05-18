<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Adminhtml\Subscription;

use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;

class Cancel extends Action
{
    public const ADMIN_RESOURCE = 'Byte8_StockRadar::subscription';

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        Action\Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);

        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid subscription ID.'));
            return $redirect->setPath('*/*/index');
        }

        $changed = $this->subscriptionService->cancelByIds([$id]);
        if ($changed > 0) {
            $this->messageManager->addSuccessMessage(__('Subscription cancelled.'));
        } else {
            $this->messageManager->addNoticeMessage(
                __('Subscription was already cancelled or in a terminal state.')
            );
        }

        return $redirect->setPath('*/*/index');
    }
}
