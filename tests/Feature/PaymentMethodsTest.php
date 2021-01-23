<?php

namespace Laravel\Braintree\Tests\Feature;

use Braintree\PaymentMethod as BraintreePaymentMethod;
use Laravel\Braintree\PaymentMethod;

class PaymentMethodsTest extends FeatureTestCase
{
    public function test_we_can_add_payment_methods()
    {
        $user = $this->createCustomer('we_can_add_payment_methods');
        $user->createAsBraintreeCustomer();

        $paymentMethod = $user->addPaymentMethod('fake-valid-amex-nonce');

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('American Express', $paymentMethod->cardType);
        $this->assertEquals('0005', $paymentMethod->last4);
        $this->assertTrue($user->hasPaymentMethod());
        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_we_can_remove_payment_methods()
    {
        $user = $this->createCustomer('we_can_remove_payment_methods');
        $user->createAsBraintreeCustomer();

        $paymentMethod = $user->addPaymentMethod('fake-valid-visa-nonce');

        $this->assertCount(1, $user->paymentMethods());
        $this->assertTrue($user->hasPaymentMethod());

        $user->removePaymentMethod($paymentMethod->asBraintreePaymentMethod());

        $this->assertCount(0, $user->paymentMethods());
        $this->assertFalse($user->hasPaymentMethod());
    }

    public function test_we_can_remove_the_default_payment_method()
    {
        $user = $this->createCustomer('we_can_remove_the_default_payment_method');
        $user->createAsBraintreeCustomer();

        $paymentMethod = $user->updateDefaultPaymentMethod('fake-valid-amex-nonce');

        $this->assertCount(1, $user->paymentMethods());
        $this->assertTrue($user->hasPaymentMethod());
        $this->assertTrue($user->hasDefaultPaymentMethod());

        $user->removePaymentMethod($paymentMethod->asBraintreePaymentMethod());

        $this->assertCount(0, $user->paymentMethods());
        $this->assertNull($user->defaultPaymentMethod());
        $this->assertNull($user->card_brand);
        $this->assertNull($user->card_last_four);
        $this->assertFalse($user->hasPaymentMethod());
        $this->assertFalse($user->hasDefaultPaymentMethod());
    }

    public function test_we_can_set_a_default_payment_method()
    {
        $user = $this->createCustomer('we_can_set_a_default_payment_method');
        $user->createAsBraintreeCustomer();

        $paymentMethod = $user->updateDefaultPaymentMethod('fake-valid-amex-nonce');

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('American Express', $paymentMethod->cardType);
        $this->assertEquals('0005', $paymentMethod->last4);
        $this->assertTrue($user->hasDefaultPaymentMethod());

        $paymentMethod = $user->defaultPaymentMethod();

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('American Express', $paymentMethod->cardType);
        $this->assertEquals('0005', $paymentMethod->last4);
    }

    public function test_we_can_retrieve_all_payment_methods()
    {
        $user = $this->createCustomer('we_can_retrieve_all_payment_methods');
        $user->createAsBraintreeCustomer();

        BraintreePaymentMethod::create([
            'customerId' => $user->braintreeId(),
            'paymentMethodNonce' => 'fake-valid-visa-nonce',
        ]);

        BraintreePaymentMethod::create([
            'customerId' => $user->braintreeId(),
            'paymentMethodNonce' => 'fake-valid-amex-nonce',
        ]);

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(2, $paymentMethods);
        $this->assertEquals('American Express', $paymentMethods->first()->cardType);
        $this->assertEquals('Visa', $paymentMethods->last()->cardType);
    }

    public function test_we_can_sync_the_default_payment_method_from_braintree()
    {
        $user = $this->createCustomer('we_can_sync_the_payment_method_from_stripe');
        $user->createAsBraintreeCustomer();

        BraintreePaymentMethod::create([
            'customerId' => $user->braintreeId(),
            'paymentMethodNonce' => 'fake-valid-visa-nonce',
        ]);

        $user->refresh();

        $this->assertNull($user->card_brand);
        $this->assertNull($user->card_last_four);

        $user = $user->updateDefaultPaymentMethodFromBraintree();

        $this->assertEquals('Visa', $user->card_brand);
        $this->assertEquals('1881', $user->card_last_four);
    }

    public function test_we_delete_all_payment_methods()
    {
        $user = $this->createCustomer('we_delete_all_payment_methods');
        $user->createAsBraintreeCustomer();

        BraintreePaymentMethod::create([
            'customerId' => $user->braintreeId(),
            'paymentMethodNonce' => 'fake-valid-visa-nonce',
        ]);

        BraintreePaymentMethod::create([
            'customerId' => $user->braintreeId(),
            'paymentMethodNonce' => 'fake-valid-amex-nonce',
        ]);

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(2, $paymentMethods);

        $user->deletePaymentMethods();

        $paymentMethods = $user->paymentMethods();

        $this->assertCount(0, $paymentMethods);
    }
}