<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

/**
 * An agent arrives with a credential. The merchant and the agent reference both
 * come from it: the host decided agency from `hasRole(['super_admin','admin'])`,
 * which is a name and not a merchant, and no route here names an agent at all.
 */
abstract class AgentController extends Controller
{
    /** @var array<string, string> method => the ability it requires */
    protected array $scopes = [];

    private string $agentRef = '';

    /** @return array<string, string> */
    public function scopes(): array
    {
        return $this->scopes;
    }

    protected function admit(string $method): ?JsonResponse
    {
        $scope = $this->scopes[$method] ?? null;

        if ($scope === null) {
            return $this->refuse(403, 'insufficient_scope', 'This operation publishes no ability and cannot be called.');
        }

        $user = Request::user();

        if (! $user instanceof Authenticatable) {
            return $this->refuse(401, 'unauthenticated', 'This endpoint requires an authenticated actor.');
        }

        if (! method_exists($user, 'tokenCan') || $user->tokenCan($scope) !== true) {
            return $this->refuse(403, 'insufficient_scope', "This credential does not carry the [{$scope}] ability.");
        }

        $attribute = Config::get('customer-service-workspace-api.actor.tenant_attribute', 'team_id');
        $merchant = data_get($user, is_string($attribute) ? $attribute : 'team_id');

        if (! is_scalar($merchant) || (string) $merchant === '') {
            return $this->refuse(403, 'actor_has_no_merchant', 'This credential is not attached to a merchant.');
        }

        $identifier = $user->getAuthIdentifier();

        $this->holdTenant((string) $merchant);
        $this->agentRef = is_scalar($identifier) ? (string) $identifier : '';

        return null;
    }

    /** An agent takes a conversation themselves. There is no route that assigns somebody else. */
    protected function agentRef(): string
    {
        return $this->agentRef;
    }
}
