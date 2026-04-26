<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Controller\Adminhtml\Subscription;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Byte8_StockRadar::subscription';

    public function __construct(
        private readonly ForwardFactory $resultForwardFactory,
        private readonly PageFactory $resultPageFactory,
        Action\Context $context
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->getRequest()->getQuery('ajax')) {
            return $this->resultForwardFactory->create()->forward('grid');
        }

        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Byte8_StockRadar::subscription');
        $page->getConfig()->getTitle()->prepend(__('Stock Radar — Subscriptions'));
        $page->addBreadcrumb(__('Stock Radar'), __('Stock Radar'));
        $page->addBreadcrumb(__('Subscriptions'), __('Subscriptions'));

        return $page;
    }
}
