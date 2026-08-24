<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RequestAction;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\FindConversation;

/**
 * The workspace asks; the owning module acts. The request is persisted before it
 * is transmitted, so every answer here — including a refusal — carries the
 * record, and no status can be read as though nothing was written.
 */
final class ActionRequestController extends AgentController
{
    protected array $scopes = [
        'index' => Scope::ACT,
        'store' => Scope::ACT,
    ];

    public function index(string $reference, FindConversation $find): JsonResponse
    {
        $conversation = $find($this->tenantId(), $reference);

        return new JsonResponse(Present::collection(
            $conversation->actionRequests()->get()->map(static fn (Model $r): array => Present::actionRequest($r))->all(),
        ));
    }

    /**
     * `request_ref` is the caller's, unique per merchant, and it is the reason a
     * retry of a refund cannot authorise a second one.
     */
    public function store(string $reference, Request $request, FindConversation $find, RequestAction $ask): JsonResponse
    {
        $input = $this->validated($request, [
            'request_ref' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'target_ref' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ]);

        $conversation = $find($this->tenantId(), $reference);
        $requestRef = $this->string($input, 'request_ref');

        /** @var array<string, mixed> $payload */
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];

        $outcome = $ask(
            $this->tenantId(),
            $conversation,
            $requestRef,
            $this->string($input, 'kind'),
            $this->string($input, 'target_ref'),
            $this->agentRef(),
            $payload,
        );

        $record = $conversation->actionRequests()->where('request_ref', $requestRef)->first();

        return $this->respond(
            $outcome,
            $record instanceof Model ? ['request' => Present::actionRequest($record)] : [],
            201,
        );
    }
}
