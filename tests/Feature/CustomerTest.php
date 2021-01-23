<?php

namespace Laravel\Braintree\Tests\Feature;

class CustomerTest extends FeatureTestCase
{
    public function test_customers_in_stripe_can_be_updated()
    {
        $user = $this->createCustomer('customers_in_stripe_can_be_updated');
        $user->createAsBraintreeCustomer();

        $customer = $user->updateAsBraintreeCustomer(['company' => 'Mohamed`s Company']);

        $this->assertEquals('Mohamed`s Company', $customer->company);
    }
}