<?php

namespace Laravel\Braintree\Tests\Unit;

use Carbon\Carbon;
use Laravel\Braintree\Exceptions\CustomerAlreadyCreated;
use Laravel\Braintree\Exceptions\InvalidCustomer;
use Laravel\Braintree\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    public function test_customer_can_be_put_on_a_generic_trial()
    {
        $user = new User;

        $this->assertFalse($user->onGenericTrial());

        $user->trial_ends_at = $tomorrow = Carbon::tomorrow();

        $this->assertTrue($user->onGenericTrial());

        $user->trial_ends_at = Carbon::today()->subDays(5);

        $this->assertFalse($user->onGenericTrial());
    }

    public function test_we_can_determine_if_it_has_a_payment_method()
    {
        $user = new User;
        $user->card_brand = 'visa';

        $this->assertTrue($user->hasDefaultPaymentMethod());

        $user = new User;

        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_default_payment_method_returns_null_when_the_user_is_not_a_customer_yet()
    {
        $user = new User;

        $this->assertNull($user->defaultPaymentMethod());
    }

    public function test_braintree_customer_method_throws_exception_when_braintree_id_is_not_set()
    {
        $user = new User;

        $this->expectException(InvalidCustomer::class);

        $user->asbraintreeCustomer();
    }

    public function test_braintree_customer_cannot_be_created_when_braintree_id_is_already_set()
    {
        $user = new User();
        $user->braintree_id = 'foo';

        $this->expectException(CustomerAlreadyCreated::class);

        $user->createAsbraintreeCustomer();
    }
}