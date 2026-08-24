<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\AgentController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use LogicException;

/** Two conditions the shipped routes cannot reach, exercised rather than argued about. */
final class ProbeController extends AgentController
{
    protected array $scopes = ['explodes' => Scope::READ];

    /** Routed, and publishing no ability. */
    public function unpublished(): JsonResponse
    {
        return new JsonResponse(['data' => 'this should never be reached']);
    }

    public function explodes(): JsonResponse
    {
        throw new LogicException('something nobody planned for');
    }
}
