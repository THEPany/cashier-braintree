<?php

namespace Laravel\Braintree\Tests;

use Laravel\Braintree\CashierServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadLaravelMigrations();
    }

    protected function getPackageProviders($app)
    {
        return [CashierServiceProvider::class];
    }
}