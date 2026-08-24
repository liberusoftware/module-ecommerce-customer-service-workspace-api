<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Failure;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;

/** @return array<int, string> */
function domainExceptions(): array
{
    $namespace = 'Liberu\\Ecommerce\\CustomerServiceWorkspace\\Exceptions\\';

    return array_map(static fn (string $name): string => $namespace.$name, [
        'InvalidBody',
        'InvalidTransition',
        'NotFound',
        'RecordsAreAppendOnly',
    ]);
}

it('names only exception classes that actually exist', function (): void {
    foreach ([...domainExceptions(), 'Liberu\\Ecommerce\\CustomerServiceWorkspace\\Exceptions\\CustomerServiceWorkspaceException'] as $class) {
        expect(class_exists($class))->toBeTrue("[{$class}] does not autoload.");
    }
});

it('maps every exception the domain publishes, and exactly four of them', function (): void {
    $unmapped = array_values(array_diff(domainExceptions(), array_keys(Failure::map())));
    $unknown = array_values(array_diff(array_keys(Failure::map()), domainExceptions()));

    expect($unmapped)->toBe([])
        ->and($unknown)->toBe([])
        ->and(domainExceptions())->toHaveCount(4);
});

it('never maps the abstract base, which would swallow every exception added later', function (): void {
    expect(array_keys(Failure::map()))
        ->not->toContain('Liberu\\Ecommerce\\CustomerServiceWorkspace\\Exceptions\\CustomerServiceWorkspaceException');
});

it('classifies every mapping', function (): void {
    foreach (Failure::map() as $class => $mapping) {
        expect($mapping)->toHaveKeys(['status', 'code', 'message', 'resubmittable'], $class)
            ->and($mapping['resubmittable'])->toBeBool()
            ->and($mapping['status'])->toBeGreaterThanOrEqual(400)
            ->and($mapping['status'])->toBeLessThan(600)
            ->and($mapping['message'])->not->toBe('');
    }
});

it('answers every unknown reference identically, whoever it belongs to', function (): void {
    $notFound = array_values(array_filter(Failure::map(), static fn (array $m): bool => $m['status'] === 404));

    expect($notFound)->toHaveCount(1)
        ->and($notFound[0]['code'])->toBe('not_found')
        ->and($notFound[0]['resubmittable'])->toBeFalse();

    // Nothing in the message names what kind of thing was missing, whether a
    // claim was wrong, or whether it exists at another merchant.
    foreach (['conversation', 'message', 'note', 'claim', 'merchant', 'another'] as $tell) {
        expect(strtolower($notFound[0]['message']))->not->toContain($tell);
    }
});

it('maps no exception to a 403, which would confirm a record exists', function (): void {
    foreach (Failure::map() as $class => $mapping) {
        expect($mapping['status'])->not->toBe(403, $class);
    }
});

it('classifies all nine refusal reasons the domain can return, in its own order', function (): void {
    $reasons = array_column(RefusalReason::cases(), 'value');

    expect(array_keys(Failure::refusals()))->toBe($reasons)
        ->and($reasons)->toHaveCount(9);

    foreach (RefusalReason::cases() as $reason) {
        expect(Failure::refusal($reason))->toHaveKeys(['status', 'code', 'message', 'resubmittable']);
    }
});

it('gives every refusal its own code and its own status band', function (): void {
    $statuses = array_column(Failure::refusals(), 'status', 'code');

    expect($statuses)->toBe([
        'illegal_transition' => 409,
        'conversation_closed' => 409,
        'not_resolved' => 409,
        'score_out_of_range' => 422,
        'author_may_not_write' => 409,
        'no_gateway_bound' => 503,
        'gateway_unreachable' => 502,
        'gateway_refused' => 409,
        'retention_not_configured' => 503,
    ]);

    expect(array_keys($statuses))->toHaveCount(count(Failure::refusals()));
});

it('spends only the refusals a caller cannot change their way out of', function (): void {
    // Binding a gateway, reaching it, and resolving a conversation are all
    // things that become true later. Being told no is not.
    expect(array_column(Failure::refusals(), 'resubmittable', 'code'))->toBe([
        'illegal_transition' => false,
        'conversation_closed' => false,
        'not_resolved' => true,
        'score_out_of_range' => true,
        'author_may_not_write' => false,
        'no_gateway_bound' => true,
        'gateway_unreachable' => true,
        'gateway_refused' => false,
        'retention_not_configured' => true,
    ]);
});

it('says on every deployment refusal that nothing was sent or written', function (): void {
    foreach ([RefusalReason::GatewayUnbound, RefusalReason::GatewayUnreachable] as $reason) {
        expect(strtolower(Failure::refusal($reason)['message']))->toContain('recorded');
    }

    expect(strtolower(Failure::refusal(RefusalReason::RetentionNotConfigured)['message']))
        ->toContain('nothing was redacted');
});

it('renders no domain exception message to a caller', function (): void {
    // `InvalidTransition` names the conversation reference. Every message a
    // caller reads is a constant of ours.
    foreach ((array) glob(dirname(__DIR__, 2).'/src/Http/Controllers/*.php') as $file) {
        expect(php_strip_whitespace((string) $file))->not->toContain('getMessage()');
    }
});

it('lets an unmapped throwable bubble rather than dressing it as a 4xx', function (): void {
    expect(Failure::for(new LogicException('something nobody planned for')))->toBeNull();
});

it('shapes every error body the same way', function (): void {
    expect(Failure::body('a_code', 'A message.', true))
        ->toBe(['error' => ['code' => 'a_code', 'message' => 'A message.', 'resubmittable' => true]]);
});
