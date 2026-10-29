<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote;
use Zero\RewardPoints\Model\CartPoints;
use Zero\RewardPoints\Model\Config;

/**
 * The reward points box in Zero Checkout's order summary.
 */
class Checkout implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartPoints $cartPoints,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->cartPoints->isAvailable($this->quote());
    }

    public function balance(): int
    {
        return $this->cartPoints->balance($this->quote());
    }

    public function used(): int
    {
        return $this->cartPoints->used($this->quote());
    }

    public function usedAmount(): string
    {
        return $this->priceCurrency->format($this->cartPoints->usedAmount($this->quote()), false);
    }

    /**
     * @return array{points: int, amount: string} what the whole balance would take off this order
     */
    public function usable(): array
    {
        $use = $this->cartPoints->usable($this->quote());

        return [
            'points' => $use['points'],
            'amount' => $this->priceCurrency->format($use['amount'], false, PriceCurrencyInterface::DEFAULT_PRECISION, $this->quote()->getStore()),
        ];
    }

    public function minPoints(): int
    {
        return $this->config->minPoints($this->websiteId());
    }

    public function maxPercent(): float
    {
        return $this->config->maxPercent($this->websiteId());
    }

    private function quote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    private function websiteId(): int
    {
        return (int) $this->quote()->getStore()->getWebsiteId();
    }
}
