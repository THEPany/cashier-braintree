<?php

namespace Laravel\Braintree\Tests\Feature;

class ChargesTest extends FeatureTestCase
{
    public function test_customer_can_be_charged()
    {
        $user = $this->createCustomer('customer_can_be_charged');
        $user->createAsBraintreeCustomer();

        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');

        $response = $user->charge(1000);

        $this->assertEquals(1000, $response->transaction->amount);
        $this->assertEquals($user->braintreeId(), $response->transaction->customer['id']);
    }

    public function test_non_braintree_customer_can_be_charged()
    {
        $user = $this->createCustomer('non_braintree_customer_can_be_charged');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Braintree was unable to perform a charge: Cannot determine payment method.");

        $user->charge(1000);
    }

    public function test_customer_can_be_charged_and_invoiced_immediately()
    {
        $user = $this->createCustomer('customer_can_be_charged_and_invoiced_immediately');
        $user->createAsBraintreeCustomer();
        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');
        
        $user->invoiceFor('Laravel Cashier', 10);

        $invoice = $user->invoicesIncludingPending()[0];
        $foundInvoice = $user->findInvoice($invoice->id);

        $this->assertEquals($invoice->id, $foundInvoice->id);
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $foundInvoice->customFields['description']);
    }
}