<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** An authenticated user carrying no abilities at all: not every guard mints tokens. */
class CredentiallessActor extends Authenticatable
{
    protected $table = 'api_actors';

    protected $guarded = [];
}
