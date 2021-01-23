<?php

namespace Laravel\Braintree\Tests\Feature;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Laravel\Braintree\Tests\Fixtures\User;
use Laravel\Braintree\Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    /**
     * @var string
     */
    protected static $planId = 'monthly-10-1';

    /**
     * @var string
     */
    protected static $otherPlanId = 'monthly-10-2';

    /**
     * @var string
     */
    protected static $otherYearPlanId = 'yearly-100-1';

    /**
     * @var string
     */
    protected static $couponId = 'coupon-1';

    protected function setUp(): void
    {
        // Delay consecutive tests to prevent Stripe rate limiting issues.
        sleep(3);

        parent::setUp();

        Eloquent::unguard();

        $this->artisan('migrate')->run();
    }

    protected function createCustomer($description = 'Cristian', $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@cashier-test.com",
            'name' => 'Cristian Gomez',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }
}