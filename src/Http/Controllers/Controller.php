<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Failure;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Throwable;

/**
 * The merchant, the domain's refusals and the one place a throwable is still
 * visible. Who is admitted is decided by the two subclasses, and a route belongs
 * to exactly one of them.
 */
abstract class Controller extends BaseController
{
    private string $tenantId = '';

    /**
     * @param  string  $method
     * @param  array<string, mixed>  $parameters
     */
    public function callAction($method, $parameters): mixed
    {
        $refusal = $this->admit($method);

        if ($refusal instanceof JsonResponse) {
            return $refusal;
        }

        try {
            return parent::callAction($method, $parameters);
        } catch (ValidationException $exception) {
            $body = Failure::body('validation_failed', 'The request did not satisfy this endpoint.', true);
            $body['error']['fields'] = $exception->errors();

            return new JsonResponse($body, 422);
        } catch (Throwable $exception) {
            $mapping = Failure::for($exception);

            if ($mapping === null) {
                throw $exception;
            }

            return new JsonResponse(
                Failure::body($mapping['code'], $mapping['message'], $mapping['resubmittable']),
                $mapping['status'],
            );
        }
    }

    /** Null admits the caller; a response refuses them before the action runs. */
    abstract protected function admit(string $method): ?JsonResponse;

    protected function tenantId(): string
    {
        return $this->tenantId;
    }

    protected function holdTenant(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * A refusal that wrote nothing carries `error` alone; one that wrote a record
     * carries `data` beside it, because reading the status alone would say the
     * opposite of what happened.
     *
     * @param  array<string, mixed>  $record
     */
    protected function respond(Outcome $outcome, array $record = [], int $recordedStatus = 200): JsonResponse
    {
        if ($outcome->reason !== null) {
            $refusal = Failure::refusal($outcome->reason);
            $body = Failure::body($refusal['code'], $refusal['message'], $refusal['resubmittable']);

            return new JsonResponse($record === [] ? $body : $body + ['data' => $record], $refusal['status']);
        }

        return new JsonResponse(
            ['data' => Present::outcome($outcome) + $record],
            $outcome->happened() ? $recordedStatus : 200,
        );
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validated(HttpRequest $request, array $rules): array
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($request->all(), $rules)->validate();

        return $validated;
    }

    /** @param  array<string, mixed>  $input */
    protected function string(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param  array<string, mixed>  $input */
    protected function nullableString(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return $value === null || $value === '' ? null : $this->string($input, $key);
    }

    /** @param  array<string, mixed>  $input */
    protected function integer(array $input, string $key): int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    protected function refuse(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(Failure::body($code, $message, false), $status);
    }
}
