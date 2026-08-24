<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** What a host's channel middleware does: resolve the request to a merchant and a person. */
final class ResolveTestStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['X-Test-Merchant' => 'tenant_id', 'X-Test-Participant' => 'participant_ref'] as $header => $attribute) {
            $value = $request->headers->get($header);

            if (is_string($value) && $value !== '') {
                $request->attributes->set($attribute, $value);
            }
        }

        /** @var Response */
        return $next($request);
    }
}
