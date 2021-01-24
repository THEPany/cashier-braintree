<?php

namespace Laravel\Braintree\Tests\Feature;

use Braintree;
use Carbon\Carbon;
use Laravel\Braintree\Cashier;
use Laravel\Braintree\Tests\Fixtures\User;

class SubscriptionsTest extends FeatureTestCase
{
    public function test_subscriptions_can_be_created()
    {
        $user = $this->createCustomer();

        // Create Subscription
        $user->newSubscription('main', static::$planId)->create('fake-valid-visa-nonce');

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->braintree_id);

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToPlan(static::$planId, 'main'));
        $this->assertFalse($user->subscribedToPlan(static::$otherPlanId, 'something'));
        $this->assertFalse($user->subscribedToPlan(static::$otherYearPlanId, 'main'));
        $this->assertTrue($user->subscribed('main', static::$planId));
        $this->assertFalse($user->subscribed('main', static::$otherPlanId));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertTrue($user->subscription('main')->recurring());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertFalse($user->subscription('main')->ended());

        // Cancel Subscription
        $subscription = $user->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Increment & Decrement
        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);

        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);

        // Swap Plan and invoice immediately.
        $subscription->swap(static::$otherPlanId);

        $this->assertEquals(static::$otherPlanId, $subscription->braintree_plan);

        // Invoice Tests
        $invoice = $user->invoicesIncludingPending()[0];
        $foundInvoice = $user->findInvoice($invoice->id);

        $this->assertEquals($invoice->id, $foundInvoice->id);
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertEquals(0, count($invoice->coupons()));
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_swapping_subscription_with_coupon()
    {
        $user = $this->createCustomer('swapping_subscription_with_coupon');
        $user->newSubscription('main', static::$planId)->create('fake-valid-visa-nonce');
        $subscription = $user->subscription('main');

        $subscription
            ->swap(static::$otherPlanId)
            ->applyCoupon(static::$couponId);

        $this->assertEquals(static::$couponId, collect($subscription->asBraintreeSubscription()->discounts)->first()->id);
    }

    public function test_creating_subscription_with_coupons()
    {
        $user = $this->createCustomer();

        // Create Subscription
        $user->newSubscription('main', static::$planId)
            ->withCoupon(static::$couponId)
            ->create('fake-valid-visa-nonce');
        
        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', static::$planId));
        $this->assertFalse($user->subscribed('main', static::$otherPlanId));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoicesIncludingPending()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
        //$this->assertFalse($invoice->discountIsPercentage());
    }

    public function test_creating_subscription_with_trial()
    {
        $user = $this->createCustomer('creating_subscription_with_trial');

        // Create Subscription
        $user->newSubscription('main', static::$planId)
            ->trialDays(7)
            ->create('fake-valid-visa-nonce');

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();
        
        // Braintree trials are just cancelled out right since we have
        // no good way to cancel them and then later resume them.
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_user_without_subscriptions_can_return_its_generic_trial_end_date()
    {
        $user = new User;
        $user->trial_ends_at = $tomorrow = Carbon::tomorrow();

        $this->assertTrue($user->onGenericTrial());
        $this->assertSame($tomorrow, $user->trialEndsAt());
    }

    public function test_creating_subscription_with_explicit_trial()
    {
        $user = $this->createCustomer('creating_subscription_with_explicit_trial');

        // Create Subscription
        $user->newSubscription('main', static::$planId)
            ->trialUntil(Carbon::tomorrow()->hour(3)->minute(15))
            ->create('fake-valid-visa-nonce');

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);

        // Cancel Subscription
        $subscription->cancel();
        
        // Braintree trials are just cancelled out right since we have
        // no good way to cancel them and then later resume them.
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $user = $this->createCustomer('applying_coupons_to_existing_customers');

        $user->newSubscription('main', static::$planId)->create('fake-valid-visa-nonce');

        $user->applyCoupon(static::$couponId, 'main');

        $customer = $user->asBraintreeCustomer();

        $this->assertEquals(static::$couponId, $customer->creditCards[0]->subscriptions[0]->discounts[0]->id);
    }

    public function test_yearly_to_monthly_properly_prorates()
    {
        $owner = $this->createCustomer('yearly_to_monthly_properly_prorates');

        // Create Subscription
        $owner->newSubscription('main', static::$otherYearPlanId)->create('fake-valid-visa-nonce');

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap(static::$planId);
        $owner = $owner->fresh();

        $this->assertEquals(2, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals(static::$planId, $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('10.00', $discount->amount);
                $this->assertEquals(10, $discount->numberOfBillingCycles);

                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_monthly_to_yearly_properly_prorates()
    {
        $owner = $this->createCustomer('monthly_to_yearly_properly_prorates');

        // Create Subscription
        $owner->newSubscription('main', static::$otherYearPlanId)->create('fake-valid-visa-nonce');

        $this->assertEquals(1, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);

        // Swap To Monthly
        $owner->subscription('main')->swap(static::$planId);
        $owner = $owner->fresh();

        // Swap Back To Yearly
        $owner->subscription('main')->swap(static::$otherYearPlanId);
        $owner = $owner->fresh();

        $this->assertEquals(3, count($owner->subscriptions));
        $this->assertNotNull($owner->subscription('main')->braintree_id);
        $this->assertEquals(static::$otherYearPlanId, $owner->subscription('main')->braintree_plan);

        $braintreeSubscription = $owner->subscription('main')->asBraintreeSubscription();

        foreach ($braintreeSubscription->discounts as $discount) {
            if ($discount->id === 'plan-credit') {
                $this->assertEquals('100.00', $discount->amount);
                $this->assertEquals(1, $discount->numberOfBillingCycles);

                return;
            }
        }

        $this->fail('Proration when switching to yearly was not done properly.');
    }

    public function test_subscription_state_scopes()
    {
        $user = $this->createCustomer('subscription_state_scopes');

        $subscription = $user->subscriptions()->create([
            'name' => 'yearly',
            'braintree_id' => 'xxxx',
            'braintree_status' => Braintree\Subscription::ACTIVE,
            'braintree_plan' => 'braintree-yearly',
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);

        // Subscription is active
        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertFalse($user->subscriptions()->onTrial()->exists());
        $this->assertTrue($user->subscriptions()->notOnTrial()->exists());
        $this->assertTrue($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // Put on trial
        $subscription->update(['trial_ends_at' => Carbon::now()->addDay()]);

        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertFalse($user->subscriptions()->cancelled()->exists());
        $this->assertTrue($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // Put on grace period
        $subscription->update(['ends_at' => Carbon::now()->addDay()]);

        $this->assertTrue($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertTrue($user->subscriptions()->onGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertFalse($user->subscriptions()->ended()->exists());

        // End subscription
        $subscription->update(['ends_at' => Carbon::now()->subDay()]);

        $this->assertFalse($user->subscriptions()->active()->exists());
        $this->assertTrue($user->subscriptions()->onTrial()->exists());
        $this->assertFalse($user->subscriptions()->notOnTrial()->exists());
        $this->assertFalse($user->subscriptions()->recurring()->exists());
        $this->assertTrue($user->subscriptions()->cancelled()->exists());
        $this->assertFalse($user->subscriptions()->notCancelled()->exists());
        $this->assertFalse($user->subscriptions()->onGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->notOnGracePeriod()->exists());
        $this->assertTrue($user->subscriptions()->ended()->exists());

        // Enable past_due as active state.
        $this->assertFalse($subscription->active());
        $this->assertFalse($user->subscriptions()->active()->exists());

        Cashier::keepPastDueSubscriptionsActive();

        $subscription->update(['ends_at' => null, 'braintree_status' => Braintree\Subscription::PAST_DUE]);

        $this->assertTrue($subscription->active());
        $this->assertTrue($user->subscriptions()->active()->exists());

        // Reset deactivate past due state to default to not conflict with other tests.
        Cashier::$deactivatePastDue = true;
    }
}