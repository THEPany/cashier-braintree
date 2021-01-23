<?php

namespace Laravel\Braintree\Tests\Unit;

use Laravel\Braintree\Subscription;
use PHPUnit\Framework\TestCase;
use Braintree\Subscription as BraintreeSubscription;

class SubscriptionTest extends TestCase
{
    public function test_we_can_check_if_a_subscription_is_past_due()
    {
        $subscription = new Subscription([
            'braintree_status' => BraintreeSubscription::PAST_DUE,
        ]);

        $this->assertTrue($subscription->pastDue());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_active()
    {
        $subscription = new Subscription([
            'braintree_status' => BraintreeSubscription::ACTIVE,
        ]);

        $this->assertFalse($subscription->pastDue());
        $this->assertTrue($subscription->active());
    }

    public function test_a_past_due_subscription_is_not_valid()
    {
        $subscription = new Subscription([
            'braintree_status' => BraintreeSubscription::PAST_DUE,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_active_subscription_is_valid()
    {
        $subscription = new Subscription([
            'braintree_status' => BraintreeSubscription::ACTIVE,
        ]);

        $this->assertTrue($subscription->valid());
    }
}