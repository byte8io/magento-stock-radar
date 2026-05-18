<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Adminhtml\Subscription;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription\CollectionFactory;
use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassCancel extends Action
{
    public const ADMIN_RESOURCE = 'Byte8_StockRadar::subscription';

    public function __construct(
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly SubscriptionService $subscriptionService,
        Action\Context $context
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $ids = array_map(
            static fn ($item) => (int) $item->getData(SubscriptionInterface::ENTITY_ID),
            $collection->getItems()
        );

        $changed = $this->subscriptionService->cancelByIds($ids);
        $skipped = count($ids) - $changed;

        if ($changed > 0) {
            $this->messageManager->addSuccessMessage(
                __('Cancelled %1 subscription(s).', $changed)
            );
        }
        if ($skipped > 0) {
            $this->messageManager->addNoticeMessage(
                __('Skipped %1 subscription(s) already in a terminal state.', $skipped)
            );
        }
        if ($changed === 0 && $skipped === 0) {
            $this->messageManager->addNoticeMessage(__('No subscriptions selected.'));
        }

        return $redirect->setPath('*/*/index');
    }
}
