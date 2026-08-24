<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\AgentController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\ActionGateway;
use Liberu\Ecommerce\CustomerServiceWorkspace\Contracts\TimelineSource;

/** @return array<int, RoutingRoute> */
function moduleRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/customer-service'),
    ));
}

function signature(RoutingRoute $route): string
{
    return implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
}

function isParticipantRoute(RoutingRoute $route): bool
{
    return str_starts_with($route->uri(), 'api/customer-service/participant/conversations');
}

it('mounts every route under the configured prefix and name', function (): void {
    expect(moduleRoutes())->toHaveCount(23);

    foreach (moduleRoutes() as $route) {
        expect($route->getName())->toStartWith('customer-service-api.')
            ->and($route->uri())->toStartWith('api/customer-service/');
    }
});

it('defaults the group middleware to an empty array rather than to null', function (): void {
    expect(Config::get('customer-service-workspace-api.route.middleware'))->toBe([])
        ->and(Config::get('customer-service-workspace-api.route.domain'))->toBeNull()
        ->and(Config::get('customer-service-workspace-api.route.prefix'))->toBe('api/customer-service');
});

/*
 * The host's fault 16, closed here: `getMessages` and `getBySession` returned a
 * transcript and a customer's email with no limit. Every route carries one, so
 * there is no list of which ones deserve it to fall behind.
 */
it('throttles every route it registers', function (): void {
    foreach (moduleRoutes() as $route) {
        $throttles = array_values(array_filter(
            $route->gatherMiddleware(),
            static fn (mixed $middleware): bool => is_string($middleware) && str_contains($middleware, 'ThrottleRequests:'),
        ));

        expect($throttles)->toHaveCount(1, $route->uri());
    }
});

it('ships a limit for each of the three bands, and none of them empty', function (): void {
    $throttle = Config::get('customer-service-workspace-api.throttle');

    expect($throttle)->toBe(['agent' => '120,1', 'participant' => '30,1', 'open' => '5,1']);

    // Opening mints a queue row and a secret served once, so it is the tightest.
    expect((int) explode(',', (string) Config::get('customer-service-workspace-api.throttle.open'))[0])
        ->toBeLessThan((int) explode(',', (string) Config::get('customer-service-workspace-api.throttle.participant'))[0]);
});

it('gives the participant a tighter limit than the agent, because one of them has a credential', function (): void {
    $limit = static fn (string $band): int => (int) explode(',', (string) Config::get("customer-service-workspace-api.throttle.{$band}"))[0];

    expect($limit('participant'))->toBeLessThan($limit('agent'));
});

it('splits the two actor families mechanically, so no route can serve both', function (): void {
    foreach (moduleRoutes() as $route) {
        $controller = $route->getController();

        if (isParticipantRoute($route)) {
            expect($controller)->toBeInstanceOf(ParticipantController::class, $route->uri());

            continue;
        }

        expect($controller)->toBeInstanceOf(AgentController::class, $route->uri());
    }
});

it('publishes no ability on a participant route and requires one on every other', function (): void {
    foreach (moduleRoutes() as $route) {
        $controller = $route->getController();

        if ($controller instanceof ParticipantController) {
            expect(method_exists($controller, 'scopes'))->toBeFalse($route->uri());

            continue;
        }

        expect($controller)->toBeInstanceOf(AgentController::class);
        expect($controller->scopes()[$route->getActionMethod()] ?? null)->toBeIn(Scope::all(), $route->uri());
    }
});

it('takes no merchant identifier in any path', function (): void {
    foreach (moduleRoutes() as $route) {
        expect($route->uri())->not->toContain('tenant')
            ->not->toContain('team')
            ->not->toContain('merchant')
            ->not->toContain('store');
    }
});

it('never names the claim in a path or a query string', function (): void {
    // The host put its only secret in a path, where it landed in every access
    // log and every Referer. This one arrives in a header and nowhere else.
    foreach (moduleRoutes() as $route) {
        expect($route->uri())->not->toContain('claim');

        foreach ($route->parameterNames() as $name) {
            expect($name)->toBeIn(['reference', 'subject_kind', 'subject_ref'], $route->uri());
        }
    }

    foreach (concreteControllers() as $file) {
        $source = php_strip_whitespace((string) $file);

        expect($source)->not->toContain('->query(')
            ->not->toContain("'claim'")
            ->not->toContain('claim_hash');
    }
});

it('reads the merchant from one place per family, and from a request nowhere', function (): void {
    foreach (concreteControllers() as $file) {
        $source = php_strip_whitespace((string) $file);

        foreach (['tenant_id', 'team_id', 'merchant_id', 'store_id', 'tenant_attribute'] as $key) {
            expect($source)->not->toContain($key, (string) $file);
        }

        expect($source)->toContain('$this->tenantId()');
    }
});

it('binds no route model and resolves a reference as a string', function (): void {
    foreach (moduleRoutes() as $route) {
        foreach ($route->signatureParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                expect($type->getName())->not->toContain('CustomerServiceWorkspace\\Models');
            }
        }
    }
});

it('keeps the four abilities apart', function (): void {
    $byScope = [];

    foreach (moduleRoutes() as $route) {
        $controller = $route->getController();

        if ($controller instanceof ParticipantController) {
            continue;
        }

        expect($controller)->toBeInstanceOf(AgentController::class);
        $byScope[(string) ($controller->scopes()[$route->getActionMethod()] ?? '')][] = signature($route);
    }

    foreach ($byScope as $scope => $routes) {
        sort($routes);
        $byScope[$scope] = $routes;
    }

    expect($byScope[Scope::READ])->toBe([
        'GET api/customer-service/conversations/{reference}',
        'GET api/customer-service/conversations/{reference}/measurement',
        'GET api/customer-service/conversations/{reference}/messages',
        'GET api/customer-service/measurement',
        'GET api/customer-service/notes/{subject_kind}/{subject_ref}',
        'GET api/customer-service/queue',
        'GET api/customer-service/timelines/{subject_kind}/{subject_ref}',
    ]);

    expect($byScope[Scope::WORK])->toBe([
        'POST api/customer-service/conversations/{reference}/abandonment',
        'POST api/customer-service/conversations/{reference}/assignment',
        'POST api/customer-service/conversations/{reference}/messages',
        'POST api/customer-service/conversations/{reference}/read-receipts',
        'POST api/customer-service/conversations/{reference}/resolution',
        'POST api/customer-service/notes/{subject_kind}/{subject_ref}',
    ]);

    // Asking another module to move money is not answering a customer.
    expect($byScope[Scope::ACT])->toBe([
        'GET api/customer-service/conversations/{reference}/action-requests',
        'POST api/customer-service/conversations/{reference}/action-requests',
    ]);

    expect($byScope[Scope::PRIVACY])->toBe([
        'POST api/customer-service/erasures',
        'POST api/customer-service/participant-records',
        'POST api/customer-service/redactions',
    ]);
});

it('routes the participant to exactly five operations', function (): void {
    $participant = [];

    foreach (moduleRoutes() as $route) {
        if ($route->getController() instanceof ParticipantController) {
            $participant[] = signature($route);
        }
    }

    sort($participant);

    expect($participant)->toBe([
        'GET api/customer-service/participant/conversations/{reference}',
        'GET api/customer-service/participant/conversations/{reference}/messages',
        'POST api/customer-service/participant/conversations',
        'POST api/customer-service/participant/conversations/{reference}/messages',
        'POST api/customer-service/participant/conversations/{reference}/rating',
    ]);
});

it('publishes its config for a host to override', function (): void {
    expect(file_exists(dirname(__DIR__, 2).'/config/customer-service-workspace-api.php'))->toBeTrue()
        ->and(Config::get('customer-service-workspace-api.actor'))->toBe(['tenant_attribute' => 'team_id'])
        ->and(Config::get('customer-service-workspace-api.participant'))
        ->toBe(['tenant_attribute' => 'tenant_id', 'ref_attribute' => 'participant_ref']);
});

it('binds no seam, and ships no adapter', function (): void {
    expect(Config::get('customer-service-workspace.seams.actions'))->toBeNull()
        ->and(app()->bound(ActionGateway::class))->toBeFalse()
        ->and(app()->bound(TimelineSource::class))->toBeFalse();

    expect(sourceFiles())->toHaveCount(17);

    foreach (sourceFiles() as $file) {
        expect(php_strip_whitespace($file))->not->toContain('Http::')
            ->not->toContain('curl_');
    }
});
