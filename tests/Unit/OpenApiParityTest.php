<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\AgentController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Failure;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\OpenApi;

/** @return array<string, RoutingRoute> */
function routed(): array
{
    $prefix = 'api/customer-service';
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), $prefix)) {
            continue;
        }

        $path = '/'.ltrim(substr($route->uri(), strlen($prefix)), '/');

        foreach ($route->methods() as $method) {
            if ($method === 'HEAD') {
                continue;
            }

            $routes[strtolower($method).' '.rtrim($path, '/')] = $route;
        }
    }

    return $routes;
}

/** @return array<string, array<string, mixed>> */
function documented(): array
{
    $operations = [];

    foreach (OpenApi::document()['paths'] as $path => $item) {
        foreach ($item as $method => $operation) {
            if ($method === 'parameters') {
                continue;
            }

            $operations[$method.' '.$path] = $operation;
        }
    }

    return $operations;
}

/** @return array<int, string> */
function statuses(array $operation): array
{
    return array_map(strval(...), array_keys($operation['responses']));
}

it('ships a valid OpenAPI 3.1 document at the package version', function (): void {
    $document = OpenApi::document();
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/module.json'), true);

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['info']['version'])->toBe($manifest['version'])
        ->and($document['paths'])->not->toBeEmpty()
        ->and(OpenApi::path())->toBeFile();
});

it('documents every route it registers', function (): void {
    expect(array_values(array_diff(array_keys(routed()), array_keys(documented()))))->toBe([]);
});

it('registers a route for every operation it documents', function (): void {
    expect(array_values(array_diff(array_keys(documented()), array_keys(routed()))))->toBe([]);
});

it('documents the same ability the agent controller enforces', function (): void {
    $mismatched = [];
    $routes = routed();

    foreach (documented() as $key => $operation) {
        $controller = $routes[$key]->getController();

        if (! $controller instanceof AgentController) {
            continue;
        }

        $enforced = $controller->scopes()[$routes[$key]->getActionMethod()] ?? null;
        $declared = $operation['security'][0]['bearer'][0] ?? null;

        if ($enforced !== $declared) {
            $mismatched[$key] = ['documented' => $declared, 'enforced' => $enforced];
        }
    }

    expect($mismatched)->toBe([])
        ->and(array_keys(OpenApi::document()['components']['securitySchemes']))->toBe(['bearer', 'participantClaim']);
});

it('documents only abilities this package publishes', function (): void {
    foreach (documented() as $key => $operation) {
        if (! isset($operation['security'][0]['bearer'])) {
            continue;
        }

        expect(Scope::all())->toContain($operation['security'][0]['bearer'][0]);
    }
});

it('secures the participant with the claim and never with an ability', function (): void {
    $routes = routed();

    foreach (documented() as $key => $operation) {
        if (! $routes[$key]->getController() instanceof ParticipantController) {
            continue;
        }

        expect($operation['security'][0]['bearer'] ?? null)->toBeNull($key);

        // Opening is the one operation performed before a claim exists.
        $expected = $key === 'post /participant/conversations' ? [] : [['participantClaim' => []]];

        expect($operation['security'])->toBe($expected, $key);
    }
});

it('gives every operation an identifier, a summary, a description and a tag', function (): void {
    $ids = [];
    $tags = array_column(OpenApi::document()['tags'], 'name');

    foreach (documented() as $key => $operation) {
        expect($operation)->toHaveKeys(['operationId', 'summary', 'description', 'tags', 'responses'], $key)
            ->and($tags)->toContain($operation['tags'][0]);

        $ids[] = $operation['operationId'];
    }

    expect(array_unique($ids))->toHaveCount(count($ids));
});

it('names every path parameter its route declares', function (): void {
    $paths = OpenApi::document()['paths'];

    foreach (documented() as $key => $operation) {
        [, $path] = explode(' ', $key, 2);
        preg_match_all('/\{([a-z_]+)\}/', $path, $matches);

        $declared = array_map(
            static fn (array $parameter): string => basename((string) ($parameter['$ref'] ?? $parameter['name'] ?? '')),
            array_merge($paths[$path]['parameters'] ?? [], $operation['parameters'] ?? []),
        );

        foreach ($matches[1] as $name) {
            expect($declared)->toContain($name);
        }
    }
});

it('documents 401 and 403 on every agent operation, because every one is credentialled and scoped', function (): void {
    $routes = routed();

    foreach (documented() as $key => $operation) {
        if (! $routes[$key]->getController() instanceof AgentController) {
            continue;
        }

        expect(statuses($operation))->toContain('401')->toContain('403');
    }
});

it('documents 503 on every participant operation, because a merchant is resolved rather than sent', function (): void {
    $routes = routed();

    foreach (documented() as $key => $operation) {
        if (! $routes[$key]->getController() instanceof ParticipantController) {
            continue;
        }

        expect(statuses($operation))->toContain('503')->not->toContain('401');
    }
});

/*
 * The reference package asserted the opposite — that no operation answers 429 —
 * because it added no throttle. This one throttles every route, so promising the
 * wait is the honest document and its absence would be the drift.
 */
it('documents 429 on every operation, because every route carries a limit', function (): void {
    foreach (documented() as $key => $operation) {
        expect(statuses($operation))->toContain('429');
    }

    expect(OpenApi::document()['components']['responses']['TooManyRequests']['headers'])
        ->toHaveKey('Retry-After');
});

it('classifies every documented failure', function (): void {
    $error = OpenApi::document()['components']['schemas']['Error'];

    expect($error['properties']['error']['required'])->toContain('resubmittable');
});

it('accepts no merchant identifier anywhere in the document', function (): void {
    $document = (string) json_encode(OpenApi::document());

    expect($document)->not->toContain('tenant_id')
        ->not->toContain('team_id')
        ->not->toContain('store_id')
        ->not->toContain('merchant_id');
});

it('publishes the claim in one response and accepts it in one header', function (): void {
    $document = OpenApi::document();

    expect($document['components']['schemas']['Opened']['required'])->toBe(['reference', 'claim'])
        ->and($document['components']['schemas']['Opened']['properties']['claim']['description'])
        ->toContain('once');

    $carrying = array_keys(array_filter(
        $document['components']['schemas'],
        static fn (array $schema): bool => array_key_exists('claim', $schema['properties'] ?? []),
    ));

    expect($carrying)->toBe(['Opened']);

    expect($document['components']['securitySchemes']['participantClaim'])
        ->toBe([
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-Participant-Claim',
            'description' => 'The claim the module issued when the conversation was opened. It is served once and never again, and it is a header rather than a path segment so it does not reach an access log or a Referer.',
        ]);

    foreach ($document['paths'] as $path => $item) {
        expect((string) json_encode($item['parameters'] ?? []))->not->toContain('claim');
    }
});

it('offers no idempotency key, having reasoned it through', function (): void {
    $document = strtolower((string) json_encode(OpenApi::document()));

    expect($document)->not->toContain('idempotency-key')
        ->and($document)->not->toContain('idempotency_key');

    expect(OpenApi::document()['info']['description'])
        ->toContain('There is no idempotency key')
        ->toContain('one-time secret');

    expect(documented()['post /conversations/{reference}/action-requests']['description'])
        ->toContain('request_ref');
});

it('documents every status the failure map can produce, and no status it cannot', function (): void {
    $possible = array_map(strval(...), array_unique(array_merge(
        [200, 201, 401, 403, 429],
        array_column(Failure::map(), 'status'),
        array_column(Failure::refusals(), 'status'),
    )));

    foreach (documented() as $key => $operation) {
        foreach (statuses($operation) as $status) {
            expect($possible)->toContain($status);
        }
    }
});

it('documents every refusal code the surface can answer with', function (): void {
    $document = (string) json_encode(OpenApi::document());

    foreach ([...array_column(Failure::refusals(), 'code'), ...array_column(Failure::map(), 'code')] as $code) {
        expect($document)->toContain($code);
    }

    foreach (['no_merchant_resolved', 'no_participant_resolved', 'insufficient_scope', 'unauthenticated', 'validation_failed'] as $code) {
        expect($document)->toContain($code);
    }
});

it('says an action request that was refused still wrote the record', function (): void {
    $refused = OpenApi::document()['components']['schemas']['RefusedActionRequest'];

    expect($refused['required'])->toBe(['error', 'data'])
        ->and($refused['properties']['data']['properties']['request']['$ref'])->toBe('#/components/schemas/ActionRequest');

    $responses = documented()['post /conversations/{reference}/action-requests']['responses'];

    foreach (['502', '503', '409'] as $status) {
        expect((string) json_encode($responses[$status]))->toContain('RefusedActionRequest');
    }
});

it('says a timeline missing a source is not a short timeline', function (): void {
    $timeline = OpenApi::document()['components']['schemas']['Timeline'];

    expect($timeline['required'])->toBe(['entries', 'answered', 'skipped', 'complete'])
        ->and($timeline['properties']['skipped']['description'])->toContain('by name')
        ->and($timeline['properties']['complete']['description'])->toContain('never');

    expect(documented()['get /timelines/{subject_kind}/{subject_ref}']['description'])
        ->toContain('names the sources it could not ask');
});

it('says a null figure is not a zero, everywhere one can appear', function (): void {
    $schemas = OpenApi::document()['components']['schemas'];

    foreach (['wait_seconds', 'first_reply_seconds', 'resolution_seconds'] as $figure) {
        expect($schemas['Measurement']['properties'][$figure]['description'])->toContain('Never 0');
    }

    expect($schemas['Conversation']['properties']['queue_position']['description'])
        ->toContain('not queued')
        ->toContain('never 1');
});

it('publishes the states, the authors and the visibilities the domain defines', function (): void {
    $schemas = OpenApi::document()['components']['schemas'];

    expect($schemas['ConversationState']['enum'])->toBe(['queued', 'assigned', 'resolved', 'abandoned'])
        ->and($schemas['Author']['enum'])->toBe(['customer', 'agent', 'system'])
        ->and($schemas['NoteVisibility']['enum'])->toBe(['internal', 'customer_visible', 'system'])
        ->and($schemas['Recording']['enum'])->toBe(['recorded', 'already_recorded', 'refused']);
});

it('deletes nothing, because a transcript and a note are written once', function (): void {
    $deletes = array_values(array_filter(array_keys(documented()), static fn (string $key): bool => str_starts_with($key, 'delete ')));

    expect($deletes)->toBe([]);
});
