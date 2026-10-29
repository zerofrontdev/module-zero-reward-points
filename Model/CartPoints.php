<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Reward points on a cart: what the customer could use, what is used, and
 * the switch. The theme's checkout box (ViewModel\Checkout) and the GraphQL
 * side share it; the totals collector (Total\Points) decides the amounts.
 */
class CartPoints
{
    /** "All of them": the totals take what the order allows. */
    public const ALL = PHP_INT_MAX >> 32;

    public function __construct(
        private readonly Points $points,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function isAvailable(Quote $quote): bool
    {
        $websiteId = $this->websiteId($quote);
        if (!$quote->getCustomerId() || !$this->config->isEnabled($websiteId)) {
            return false;
        }
        $balance = $this->balance($quote);

        return $this->used($quote) > 0 || ($balance > 0 && $balance >= $this->config->minPoints($websiteId));
    }

    public function balance(Quote $quote): int
    {
        return $quote->getCustomerId() ? $this->points->balance((int) $quote->getCustomerId(), $this->websiteId($quote)) : 0;
    }

    public function used(Quote $quote): int
    {
        return (int) $quote->getData('zero_reward_points_used');
    }

    public function usedAmount(Quote $quote): float
    {
        return (float) $quote->getData('zero_reward_amount');
    }

    /**
     * @return array{points: int, base: float, amount: float} what the whole balance would take off this cart
     */
    public function usable(Quote $quote): array
    {
        $websiteId = $this->websiteId($quote);
        $eligible = 0.0;
        foreach ($quote->getAllItems() as $item) {
            if ($item->getParentItem() || ($item->getHasChildren() && $item->isChildrenCalculated())) {
                continue;
            }
            $eligible += (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();
        }
        // The points already used are in the lines' discounts: count them back in.
        $eligible += (float) $quote->getData('base_zero_reward_amount');
        $balance = $this->balance($quote);
        $use = Redemption::calculate(
            $balance,
            $balance,
            $this->config->minPoints($websiteId),
            $this->config->pointValue($websiteId),
            $eligible,
            $this->config->maxPercent($websiteId)
        );

        return $use + ['amount' => $this->priceCurrency->convertAndRound($use['base'], $quote->getStore())];
    }

    /** Use the balance on this cart, or stop using it. */
    public function apply(Quote $quote, bool $use): void
    {
        $quote->setData('zero_reward_points', $use ? self::ALL : 0);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($quote);
    }

    private function websiteId(Quote $quote): int
    {
        return (int) $quote->getStore()->getWebsiteId();
    }
}
