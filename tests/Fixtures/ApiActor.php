<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ApiActor extends Authenticatable
{
    protected $table = 'api_actors';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['abilities' => 'array'];
    }

    public function tokenCan(string $ability): bool
    {
        return in_array($ability, (array) $this->getAttribute('abilities'), true);
    }
}
