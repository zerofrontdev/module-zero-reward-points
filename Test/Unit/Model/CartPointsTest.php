<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\RewardPoints\Test\Unit\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\RewardPoints\Model\CartPoints;
use Zero\RewardPoints\Model\Config;
use Zero\RewardPoints\Model\Points;

#[AllowMockObjectsWithoutExpectations]
class CartPointsTest extends TestCase
{
    private Quote $quote;
    private CartPoints $cartPoints;
    private int $balance = 500;
    private int $saved = 0;

    protected function setUp(): void
    {
        $points = $this->createStub(Points::class);
        $points->method('balance')->willReturnCallback(fn () => $this->balance);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('minPoints')->willReturn(100);
        $config->method('pointValue')->willReturn(0.01);
        $config->method('maxPercent')->willReturn(50.0);
        $currency = $this->createStub(PriceCurrencyInterface::class);
        $currency->method('convertAndRound')->willReturnCallback(fn (float $amount) => $amount * 2);
        $repository = $this->createStub(CartRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function () {
            $this->saved++;
        });
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(2);
        $item = $this->createPartialMock(Item::class, ['getParentItem']);
        $item->setData(['base_row_total' => 80.0, 'base_discount_amount' => 0.0, 'has_children' => false]);
        $this->quote = $this->createPartialMock(Quote::class, ['getStore', 'collectTotals', 'getAllItems']);
        $this->quote->method('getStore')->willReturn($store);
        $this->quote->method('collectTotals')->willReturnSelf();
        $this->quote->method('getAllItems')->willReturn([$item]);
        $this->quote->setData('customer_id', 7);
        $this->cartPoints = new CartPoints($points, $config, $currency, $repository);
    }

    public function testUsableIsCappedByTheOrderAndConvertedToTheQuoteCurrency(): void
    {
        // 500 points = 5.00 base, under 50% of 80.00; the quote currency is at rate 2.
        $this->assertSame(['points' => 500, 'base' => 5.0, 'amount' => 10.0], $this->cartPoints->usable($this->quote));
        $this->assertTrue($this->cartPoints->isAvailable($this->quote));
    }

    public function testTooFewPointsAreNotAvailable(): void
    {
        $this->balance = 50;

        $this->assertFalse($this->cartPoints->isAvailable($this->quote));
        $this->assertSame(0, $this->cartPoints->usable($this->quote)['points']);
    }

    public function testApplyAsksForAllThePointsAndSaves(): void
    {
        $this->cartPoints->apply($this->quote, true);
        $this->assertSame(CartPoints::ALL, (int) $this->quote->getData('zero_reward_points'));

        $this->cartPoints->apply($this->quote, false);
        $this->assertSame(0, (int) $this->quote->getData('zero_reward_points'));
        $this->assertSame(2, $this->saved);
    }
}
