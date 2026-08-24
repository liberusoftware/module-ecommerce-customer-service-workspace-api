<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AbandonConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\AssignAgent;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\MarkMessagesRead;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\PostMessage;
use Liberu\Ecommerce\CustomerServiceWorkspace\Actions\ResolveConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\Author;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Message;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\FindConversation;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\QueuePosition;

final class ConversationController extends AgentController
{
    protected array $scopes = [
        'show' => Scope::READ,
        'messages' => Scope::READ,
        'reply' => Scope::WORK,
        'read' => Scope::WORK,
        'assign' => Scope::WORK,
        'resolve' => Scope::WORK,
        'abandon' => Scope::WORK,
    ];

    public function show(string $reference, FindConversation $find, QueuePosition $position): JsonResponse
    {
        $conversation = $find($this->tenantId(), $reference);

        return new JsonResponse([
            'data' => Present::conversation($conversation, $position($this->tenantId(), $conversation)),
        ]);
    }

    public function messages(string $reference, FindConversation $find): JsonResponse
    {
        $conversation = $find($this->tenantId(), $reference);

        return new JsonResponse(Present::collection(
            $conversation->messages()->get()->map(static fn (Message $message): array => Present::message($message))->all(),
        ));
    }

    /**
     * The body is `present` rather than `required`: whether an empty line is a
     * message is the domain's rule and it answers 422 with it.
     */
    public function reply(string $reference, Request $request, FindConversation $find, PostMessage $post): JsonResponse
    {
        $input = $this->validated($request, ['body' => ['present', 'string', 'max:20000']]);
        $conversation = $find($this->tenantId(), $reference);

        return $this->respond(
            $post($this->tenantId(), $conversation, Author::Agent, $this->agentRef(), $this->string($input, 'body')),
            recordedStatus: 201,
        );
    }

    public function read(string $reference, FindConversation $find, MarkMessagesRead $mark): JsonResponse
    {
        return $this->respond($mark($this->tenantId(), $find($this->tenantId(), $reference), Author::Agent));
    }

    public function assign(string $reference, FindConversation $find, AssignAgent $assign): JsonResponse
    {
        return $this->respond($assign($this->tenantId(), $find($this->tenantId(), $reference), $this->agentRef()));
    }

    public function resolve(string $reference, FindConversation $find, ResolveConversation $resolve): JsonResponse
    {
        return $this->respond($resolve($this->tenantId(), $find($this->tenantId(), $reference)));
    }

    public function abandon(string $reference, FindConversation $find, AbandonConversation $abandon): JsonResponse
    {
        return $this->respond($abandon($this->tenantId(), $find($this->tenantId(), $reference)));
    }
}
