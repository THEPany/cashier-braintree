<?php

namespace Laravel\Braintree\Tests\Feature;

use Laravel\Braintree\Exceptions\InvalidCustomer;
use Laravel\Braintree\Exceptions\InvalidInvoice;
use Laravel\Braintree\Invoice;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InvoicesTest extends FeatureTestCase
{
    public function test_require_braintree_customer_for_invoicing()
    {
        $user = $this->createCustomer('require_braintree_customer_for_invoicing');

        $this->expectException(InvalidCustomer::class);

        $user->invoices();
    }

    public function test_invoicing_fails_with_nothing_to_invoice()
    {
        $user = $this->createCustomer('invoicing_fails_with_nothing_to_invoice');
        $user->createAsBraintreeCustomer();

        $response = $user->invoices();

        $this->assertTrue($response->isEmpty());
    }

    public function test_customer_can_be_invoiced()
    {
        $user = $this->createCustomer('customer_can_be_invoiced');
        $user->createAsBraintreeCustomer();
        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');

        $response = $user->invoiceFor('Laracon', 49900);

        $this->assertInstanceOf(\Braintree\Transaction::class, $response->transaction);
        $this->assertEquals(49900, $response->transaction->amount);
    }

    public function test_find_invoice_by_id()
    {
        $user = $this->createCustomer('find_invoice_by_id');
        $user->createAsBraintreeCustomer();
        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');

        $invoice = $user->invoiceFor('Laracon', 49900);

        $invoice = $user->findInvoice($invoice->transaction->id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->rawTotal());
    }

    public function test_it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user()
    {
        $user = $this->createCustomer('it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user');
        $user->createAsBraintreeCustomer();
        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');
        
        $otherUser = $this->createCustomer('other_user');
        $otherUser->createAsBraintreeCustomer();
        
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->expectException(InvalidInvoice::class);
        $this->expectExceptionMessage(
            "The invoice `{$invoice->transaction->id}` does not belong to this customer `$otherUser->braintree_id`."
        );

        $otherUser->findInvoice($invoice->transaction->id);
    }

    public function test_find_invoice_by_id_or_fail()
    {
        $user = $this->createCustomer('find_invoice_by_id_or_fail');
        $user->createAsBraintreeCustomer();
        $user->updateDefaultPaymentMethod('fake-valid-visa-nonce');

        $otherUser = $this->createCustomer('other_user');
        $otherUser->createAsBraintreeCustomer();
        
        $invoice = $user->invoiceFor('Laracon', 49900);

        $this->expectException(AccessDeniedHttpException::class);

        $otherUser->findInvoiceOrFail($invoice->transaction->id);
    }
}