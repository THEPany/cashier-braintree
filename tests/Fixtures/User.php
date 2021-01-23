<?php

namespace Laravel\Braintree\Tests\Fixtures;

use Laravel\Braintree\Billable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    use Billable;
}