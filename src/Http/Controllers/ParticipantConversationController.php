<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\OpenConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\RecordRating;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\FindConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\QueuePosition;

/**
 * The customer's five operations. Every one after the first proves standing with
 * the claim it was issued, and a reference that does not exist, one belonging to
 * another merchant and a claim that does not match are one 404.
 */
final class ParticipantConversationController extends ParticipantController
{
    /**
     * The claim is served here and nowhere else. There is no idempotency key:
     * serving a retry would mean serving a one-time secret twice, and the domain
     * has no path that re-issues one.
     */
    public function open(Request $request, OpenConversation $open): JsonResponse
    {
        $input = $this->validated($request, [
            'channel' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $participantRef = $this->participantRef();

        if ($participantRef === null) {
            return $this->refuse(503, 'no_participant_resolved', 'This deployment named nobody for this request, so no conversation was opened.');
        }

        $opened = $open(
            $this->tenantId(),
            $this->string($input, 'channel'),
            $participantRef,
            $this->nullableString($input, 'name'),
            $this->nullableString($input, 'email'),
        );

        return new JsonResponse(['data' => Present::opened($opened)], 201);
    }

    public function show(string $reference, FindConversation $find, QueuePosition $position): JsonResponse
    {
        $conversation = $find->forClaim($this->tenantId(), $reference, $this->claim());

        return new JsonResponse([
            'data' => Present::participantConversation($conversation, $position($this->tenantId(), $conversation)),
        ]);
    }

    public function messages(string $reference, FindConversation $find): JsonResponse
    {
        $conversation = $find->forClaim($this->tenantId(), $reference, $this->claim());

        return new JsonResponse(Present::collection(
            $conversation->messages()->get()->map(static fn (Model $m): array => Present::participantMessage($m))->all(),
        ));
    }

    public function reply(string $reference, Request $request, FindConversation $find, PostMessage $post): JsonResponse
    {
        $input = $this->validated($request, ['body' => ['present', 'nullable', 'string', 'max:20000']]);
        $conversation = $find->forClaim($this->tenantId(), $reference, $this->claim());

        return $this->respond(
            $post($this->tenantId(), $conversation, Author::Customer, $conversation->participant_ref, $this->string($input, 'body')),
            recordedStatus: 201,
        );
    }

    /** The range a score may take is the domain's rule and it answers 422 with it. */
    public function rate(string $reference, Request $request, FindConversation $find, RecordRating $rate): JsonResponse
    {
        $input = $this->validated($request, [
            'score' => ['required', 'integer'],
            'feedback' => ['nullable', 'string', 'max:20000'],
        ]);

        $claim = $this->claim();
        $conversation = $find->forClaim($this->tenantId(), $reference, $claim);

        return $this->respond(
            $rate($this->tenantId(), $conversation, $claim, $this->integer($input, 'score'), $this->nullableString($input, 'feedback')),
            recordedStatus: 201,
        );
    }
}
