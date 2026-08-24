<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\ListQueue;

/** The ordering is the queue, so the list carries no position and no name. */
final class QueueController extends AgentController
{
    protected array $scopes = ['index' => Scope::READ];

    public function index(ListQueue $queue): JsonResponse
    {
        return new JsonResponse(Present::collection(
            $queue($this->tenantId())->map(static fn (Conversation $c): array => Present::queueEntry($c))->all(),
        ));
    }
}
