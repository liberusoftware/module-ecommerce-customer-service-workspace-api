<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http;

use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;
use Liberu\Ecommerce\CustomerServiceWorkspace\Exceptions\InvalidBody;
use Liberu\Ecommerce\CustomerServiceWorkspace\Exceptions\InvalidTransition;
use Liberu\Ecommerce\CustomerServiceWorkspace\Exceptions\NotFound;
use Liberu\Ecommerce\CustomerServiceWorkspace\Exceptions\RecordsAreAppendOnly;
use Throwable;

/**
 * Every way a request to this surface can fail, in two tables so both can be
 * asserted whole: every exception the domain publishes appears exactly once, and
 * every one of the nine refusal reasons is classified. A tenth case added to the
 * domain fails `Unit\FailureMapTest` rather than arriving here as a 500.
 *
 * An **exception** means the request did not happen. A **refusal** means the
 * domain answered, and for an action request it means the record was written
 * and the transmission was not — those responses carry `data` beside `error`.
 */
final class Failure
{
    /** One answer for a record this caller does not hold, whatever the reason. */
    private const NOT_FOUND = [
        'status' => 404,
        'code' => 'not_found',
        'message' => 'No such record exists here.',
        'resubmittable' => false,
    ];

    /** @var array<class-string, array{status: int, code: string, message: string, resubmittable: bool}> */
    private const MAP = [
        NotFound::class => self::NOT_FOUND,
        InvalidBody::class => [
            'status' => 422,
            'code' => 'invalid_body',
            'message' => 'A line of a transcript and a note both need something in them. An empty body is not a shorter message.',
            'resubmittable' => true,
        ],
        InvalidTransition::class => [
            'status' => 409,
            'code' => 'invalid_transition',
            'message' => 'That conversation is not in a state this operation can move it out of.',
            'resubmittable' => false,
        ],
        RecordsAreAppendOnly::class => [
            'status' => 409,
            'code' => 'append_only',
            'message' => 'A transcript and a note are written once. A correction is a new entry.',
            'resubmittable' => false,
        ],
    ];

    /**
     * Nine reasons, nine instructions. The three gateway reasons are kept apart
     * because binding an adapter, reaching the owning module and being told no
     * are three different remedies, and only the last one is spent.
     *
     * @var array<string, array{status: int, code: string, message: string, resubmittable: bool}>
     */
    private const REFUSALS = [
        RefusalReason::IllegalTransition->value => [
            'status' => 409,
            'code' => 'illegal_transition',
            'message' => 'That conversation cannot make that move. A resolved conversation is not reopened by assigning an agent to it.',
            'resubmittable' => false,
        ],
        RefusalReason::ConversationClosed->value => [
            'status' => 409,
            'code' => 'conversation_closed',
            'message' => 'That conversation is closed and nothing more is said in it.',
            'resubmittable' => false,
        ],
        RefusalReason::NotResolved->value => [
            'status' => 409,
            'code' => 'not_resolved',
            'message' => 'A rating belongs to a conversation that has been resolved.',
            'resubmittable' => true,
        ],
        RefusalReason::ScoreOutOfRange->value => [
            'status' => 422,
            'code' => 'score_out_of_range',
            'message' => 'That score is outside the range the domain accepts.',
            'resubmittable' => true,
        ],
        RefusalReason::AuthorMayNotWrite->value => [
            'status' => 409,
            'code' => 'author_may_not_write',
            'message' => 'That author may not write that here. A system line is written by the module, and an agent speaks in a conversation they have taken.',
            'resubmittable' => false,
        ],
        RefusalReason::GatewayUnbound->value => [
            'status' => 503,
            'code' => 'no_gateway_bound',
            'message' => 'This deployment has bound nothing to carry that request, so it was recorded and nothing was sent.',
            'resubmittable' => true,
        ],
        RefusalReason::GatewayUnreachable->value => [
            'status' => 502,
            'code' => 'gateway_unreachable',
            'message' => 'The module that owns that could not be reached, so the request was recorded and nothing was sent.',
            'resubmittable' => true,
        ],
        RefusalReason::GatewayRefused->value => [
            'status' => 409,
            'code' => 'gateway_refused',
            'message' => 'The module that owns that refused. The request was recorded and carries what it said.',
            'resubmittable' => false,
        ],
        RefusalReason::RetentionNotConfigured->value => [
            'status' => 503,
            'code' => 'retention_not_configured',
            'message' => 'This deployment has configured no retention window, so nothing was redacted and no policy is running.',
            'resubmittable' => true,
        ],
    ];

    /** @return array<class-string, array{status: int, code: string, message: string, resubmittable: bool}> */
    public static function map(): array
    {
        return self::MAP;
    }

    /** @return array<string, array{status: int, code: string, message: string, resubmittable: bool}> */
    public static function refusals(): array
    {
        return self::REFUSALS;
    }

    /** @return array{status: int, code: string, message: string, resubmittable: bool}|null */
    public static function for(Throwable $exception): ?array
    {
        foreach (self::MAP as $class => $mapping) {
            if ($exception instanceof $class) {
                return $mapping;
            }
        }

        return null;
    }

    /** @return array{status: int, code: string, message: string, resubmittable: bool} */
    public static function refusal(RefusalReason $reason): array
    {
        return self::REFUSALS[$reason->value];
    }

    /** @return array{error: array{code: string, message: string, resubmittable: bool}} */
    public static function body(string $code, string $message, bool $resubmittable): array
    {
        return ['error' => ['code' => $code, 'message' => $message, 'resubmittable' => $resubmittable]];
    }
}
