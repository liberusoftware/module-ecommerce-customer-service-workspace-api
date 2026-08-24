<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\WriteNote;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\NoteVisibility;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\ListNotes;

/**
 * A note's subject is an opaque reference this module never resolves, so one
 * pair of routes serves a conversation, an order or anything else annotated.
 */
final class NoteController extends AgentController
{
    protected array $scopes = [
        'index' => Scope::READ,
        'store' => Scope::WORK,
    ];

    public function index(string $subjectKind, string $subjectRef, ListNotes $notes): JsonResponse
    {
        return new JsonResponse(Present::collection(
            $notes($this->tenantId(), $subjectKind, $subjectRef)->map(static fn (Model $note): array => Present::note($note))->all(),
        ));
    }

    /**
     * `system` is accepted by the validator and refused by the domain: a system
     * note has no author, so no agent action can produce one.
     */
    public function store(string $subjectKind, string $subjectRef, Request $request, WriteNote $write): JsonResponse
    {
        $input = $this->validated($request, [
            'visibility' => ['required', Rule::in(array_column(NoteVisibility::cases(), 'value'))],
            'body' => ['present', 'nullable', 'string', 'max:20000'],
        ]);

        return $this->respond(
            $write(
                $this->tenantId(),
                $subjectKind,
                $subjectRef,
                NoteVisibility::from($this->string($input, 'visibility')),
                $this->agentRef(),
                $this->string($input, 'body'),
            ),
            recordedStatus: 201,
        );
    }
}
