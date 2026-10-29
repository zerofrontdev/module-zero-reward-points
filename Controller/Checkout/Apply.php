<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\RewardPoints\Model\CartPoints;

/**
 * "Use my points" / "Don't use" in the checkout's order summary (HTMX POST;
 * form key checked by core's CSRF validation): sets the points the cart asks
 * to use (all it can, or none), collects the totals, answers with the box
 * and the totals (out of band), as the discount code does.
 */
class Apply implements HttpPostActionInterface
{
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly RequestInterface $request,
        private readonly CartPoints $cartPoints
    ) {
    }

    /**
     * @return Layout
     */
    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() && $quote->getCustomerId()) {
            $this->cartPoints->apply($quote, $this->request->getParam('use') === '1');
        }
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement('zero.reward.checkout');
        $resultLayout->getLayout()->addOutputElement('zero.checkout.ordersummarytotals');

        return $resultLayout;
    }
}
