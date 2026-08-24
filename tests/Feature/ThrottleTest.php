<?php

declare(strict_types=1);

/*
 * Host fault 16. `getMessages` and `getBySession` returned a transcript and a
 * customer's email under no limit at all. The domain counts no requests and
 * cannot: it has no notion of a caller.
 */

it('spends the opening band and then refuses', function (): void {
    $statuses = [];

    foreach (range(1, 6) as $ignored) {
        $statuses[] = $this->withHeaders(storefront())
            ->postJson(api('participant/conversations'), ['channel' => 'chat'])
            ->getStatusCode();
    }

    expect($statuses)->toBe([201, 201, 201, 201, 201, 429]);
});

it('tells a refused caller how long to wait', function (): void {
    foreach (range(1, 6) as $ignored) {
        $response = $this->withHeaders(storefront())->postJson(api('participant/conversations'), ['channel' => 'chat']);
    }

    expect($response->headers->has('Retry-After'))->toBeTrue();
});
