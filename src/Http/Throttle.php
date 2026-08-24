<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;

/**
 * Host fault: the two endpoints that returned a transcript and a customer's
 * email carried no limit. The domain counts no requests, so the limit is here,
 * and every route in this package takes one — there is no list to fall behind.
 */
final class Throttle
{
    public const AGENT = 'agent';

    public const PARTICIPANT = 'participant';

    public const OPEN = 'open';

    public static function for(string $key): string
    {
        $limit = Config::get("customer-service-workspace-api.throttle.{$key}");

        return ThrottleRequests::class.':'.(is_string($limit) && $limit !== '' ? $limit : '30,1');
    }
}
