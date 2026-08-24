<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ConversationOpened;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ParticipantRecord;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ServiceMeasurement;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ServiceSummary;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\TimelineEntry;

/**
 * The only place a domain object becomes JSON. Nothing here reads a model class:
 * it reads attributes, so the transport is not coupled to the storage.
 *
 * Four nulls are load-bearing and none of them is ever rendered as a zero. A
 * queue position of null means not queued, which is not first. The three service
 * figures are null until the thing they measure has happened, and a zero there
 * would read as instant service. The claim appears in exactly one method.
 */
final class Present
{
    /** The agent's working view. The name and the email are why an agent opens a conversation. */
    /** @return array<string, mixed> */
    public static function conversation(Model $conversation, ?int $position): array
    {
        return self::common($conversation) + [
            'participant_ref' => self::scalar($conversation->getAttribute('participant_ref')),
            'participant_name' => self::nullableString($conversation->getAttribute('participant_name')),
            'participant_email' => self::nullableString($conversation->getAttribute('participant_email')),
            'agent_ref' => self::nullableString($conversation->getAttribute('agent_ref')),
            'assigned_at' => self::instant($conversation->getAttribute('assigned_at')),
            'first_agent_reply_at' => self::instant($conversation->getAttribute('first_agent_reply_at')),
            'forgotten_at' => self::instant($conversation->getAttribute('forgotten_at')),
            'redacted_at' => self::instant($conversation->getAttribute('redacted_at')),
            'queue_position' => $position,
        ];
    }

    /**
     * The participant's own view. It carries no name, no email and no agent
     * reference: they gave the first two and have no use for the third.
     *
     * @return array<string, mixed>
     */
    public static function participantConversation(Model $conversation, ?int $position): array
    {
        return self::common($conversation) + ['queue_position' => $position];
    }

    /** A queue entry names nobody: an agent who has not opened a conversation has no reason to read a customer's email. */
    /** @return array<string, mixed> */
    public static function queueEntry(Model $conversation): array
    {
        return self::common($conversation);
    }

    /**
     * The claim is in plaintext here and in no other method. The domain issues
     * it once and stores only its hash, so no later response can serve it again.
     *
     * @return array<string, mixed>
     */
    public static function opened(ConversationOpened $opened): array
    {
        return [
            'reference' => $opened->reference,
            'claim' => $opened->claim,
        ];
    }

    /** @return array<string, mixed> */
    public static function message(Model $message): array
    {
        return self::participantMessage($message) + [
            'author_ref' => self::nullableString($message->getAttribute('author_ref')),
            'read_at' => self::instant($message->getAttribute('read_at')),
        ];
    }

    /** A customer reads what was said, not which internal account said it. */
    /** @return array<string, mixed> */
    public static function participantMessage(Model $message): array
    {
        return [
            'sequence' => self::integer($message->getAttribute('sequence')),
            'author' => self::scalar($message->getAttribute('author')),
            'body' => self::scalar($message->getAttribute('body')),
            'sent_at' => self::instant($message->getAttribute('sent_at')),
            'redacted' => $message->getAttribute('redacted_at') !== null,
        ];
    }

    /** @return array<string, mixed> */
    public static function note(Model $note): array
    {
        return [
            'id' => self::integer($note->getAttribute('id')),
            'subject_kind' => self::scalar($note->getAttribute('subject_kind')),
            'subject_ref' => self::scalar($note->getAttribute('subject_ref')),
            'visibility' => self::scalar($note->getAttribute('visibility')),
            'author_ref' => self::nullableString($note->getAttribute('author_ref')),
            'body' => self::scalar($note->getAttribute('body')),
            'written_at' => self::instant($note->getAttribute('written_at')),
            'redacted' => $note->getAttribute('redacted_at') !== null,
        ];
    }

    /** @return array<string, mixed> */
    public static function actionRequest(Model $request): array
    {
        return [
            'request_ref' => self::scalar($request->getAttribute('request_ref')),
            'kind' => self::scalar($request->getAttribute('kind')),
            'target_ref' => self::scalar($request->getAttribute('target_ref')),
            'agent_ref' => self::nullableString($request->getAttribute('agent_ref')),
            'state' => self::scalar($request->getAttribute('state')),
            'remote_ref' => self::nullableString($request->getAttribute('remote_ref')),
            'message' => self::nullableString($request->getAttribute('message')),
            'requested_at' => self::instant($request->getAttribute('requested_at')),
            'settled_at' => self::instant($request->getAttribute('settled_at')),
        ];
    }

    /** Which of the three things happened, as a value rather than a status code alone. */
    /** @return array<string, mixed> */
    public static function outcome(Outcome $outcome): array
    {
        return [
            'recording' => $outcome->recording->value,
            'reason' => $outcome->reason?->value,
            'id' => $outcome->id,
            'count' => $outcome->count,
        ];
    }

    /** @return array<string, mixed> */
    public static function measurement(ServiceMeasurement $measurement): array
    {
        return [
            'wait_seconds' => $measurement->waitSeconds,
            'first_reply_seconds' => $measurement->firstReplySeconds,
            'resolution_seconds' => $measurement->resolutionSeconds,
            'abandoned' => $measurement->abandoned,
            'rating' => $measurement->rating,
            'measured' => $measurement->isMeasured(),
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(ServiceSummary $summary): array
    {
        return [
            'measured' => $summary->measured,
            'unmeasured' => $summary->unmeasured,
            'abandoned' => $summary->abandoned,
            'average_wait_seconds' => $summary->averageWaitSeconds,
            'average_first_reply_seconds' => $summary->averageFirstReplySeconds,
            'average_resolution_seconds' => $summary->averageResolutionSeconds,
            'average_rating' => $summary->averageRating,
            'rated' => $summary->rated,
        ];
    }

    /**
     * A source nobody answered for is named, so a short timeline cannot be read
     * as a quiet customer.
     *
     * @return array<string, mixed>
     */
    public static function timeline(Timeline $timeline): array
    {
        return [
            'entries' => array_map(self::timelineEntry(...), $timeline->entries),
            'answered' => array_values($timeline->answered),
            'skipped' => array_values($timeline->skipped),
            'complete' => $timeline->isComplete(),
        ];
    }

    /** The merchant is a property of the credential and is never published back. */
    /** @return array<string, mixed> */
    public static function participantRecord(ParticipantRecord $record): array
    {
        return [
            'participant_ref' => $record->participantRef,
            'conversations' => array_values($record->conversations),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array{data: array<int, array<string, mixed>>}
     */
    public static function collection(array $data): array
    {
        return ['data' => array_values($data)];
    }

    /** @return array<string, mixed> */
    private static function timelineEntry(TimelineEntry $entry): array
    {
        return [
            'source' => $entry->source,
            'kind' => $entry->kind,
            'occurred_at' => $entry->occurredAt->format(DATE_ATOM),
            'reference' => $entry->reference,
            'payload' => $entry->payload,
        ];
    }

    /** @return array<string, mixed> */
    private static function common(Model $conversation): array
    {
        return [
            'reference' => self::scalar($conversation->getAttribute('reference')),
            'channel' => self::scalar($conversation->getAttribute('channel')),
            'state' => self::scalar($conversation->getAttribute('state')),
            'queued_at' => self::instant($conversation->getAttribute('queued_at')),
            'resolved_at' => self::instant($conversation->getAttribute('resolved_at')),
            'abandoned_at' => self::instant($conversation->getAttribute('abandoned_at')),
        ];
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::scalar($value);
    }

    private static function scalar(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private static function instant(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $value === null ? null : self::scalar($value);
    }
}
