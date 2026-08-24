<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Present;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Outcome;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\ServiceSummary;
use Liberu\Ecommerce\CustomerServiceWorkspace\Data\Timeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Enums\RefusalReason;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\ExportParticipantRecord;
use Liberu\Ecommerce\CustomerServiceWorkspace\Queries\MeasureService;

it('renders the claim in one method and in no other', function (): void {
    $opened = openConversation();
    $conversation = conversationOf($opened);

    expect(Present::opened($opened))->toBe(['reference' => $opened->reference, 'claim' => $opened->claim]);

    $everywhereElse = (string) json_encode([
        Present::conversation($conversation, 1),
        Present::participantConversation($conversation, 1),
        Present::queueEntry($conversation),
    ]);

    expect($everywhereElse)->not->toContain($opened->claim)
        ->not->toContain('claim_hash')
        ->and($conversation->claim_hash)->not->toBe('');
});

it('names nobody in a queue entry', function (): void {
    $entry = Present::queueEntry(conversationOf(openConversation()));

    expect(array_keys($entry))->toBe(['reference', 'channel', 'state', 'queued_at', 'resolved_at', 'abandoned_at']);
});

it('shows an agent the name and the email, and shows the customer neither', function (): void {
    $conversation = conversationOf(openConversation());

    expect(Present::conversation($conversation, null))
        ->toHaveKeys(['participant_name', 'participant_email', 'agent_ref'])
        ->and(Present::conversation($conversation, null)['participant_email'])->toBe('customer@example.test');

    expect(array_keys(Present::participantConversation($conversation, null)))
        ->not->toContain('participant_name')
        ->not->toContain('participant_email')
        ->not->toContain('agent_ref');
});

it('carries a queue position of null through as null, because null is not first', function (): void {
    $conversation = conversationOf(openConversation());

    expect(Present::participantConversation($conversation, null)['queue_position'])->toBeNull()
        ->and(Present::conversation($conversation, 3)['queue_position'])->toBe(3);
});

it('leaves an unmeasured figure null rather than rendering it as instant service', function (): void {
    $measurement = (new MeasureService())('merchant-a', conversationOf(openConversation()));

    expect(Present::measurement($measurement))->toBe([
        'wait_seconds' => null,
        'first_reply_seconds' => null,
        'resolution_seconds' => null,
        'abandoned' => false,
        'rating' => null,
        'measured' => false,
    ]);
});

it('leaves every mean null when nothing has been measured', function (): void {
    expect(Present::summary(new ServiceSummary(0, 2, 0, null, null, null, null, 0)))->toBe([
        'measured' => 0,
        'unmeasured' => 2,
        'abandoned' => 0,
        'average_wait_seconds' => null,
        'average_first_reply_seconds' => null,
        'average_resolution_seconds' => null,
        'average_rating' => null,
        'rated' => 0,
    ]);
});

it('names a source nobody answered for rather than shortening the timeline in silence', function (): void {
    $rendered = Present::timeline(new Timeline([], ['notes'], ['orders', 'payments']));

    expect($rendered)->toBe([
        'entries' => [],
        'answered' => ['notes'],
        'skipped' => ['orders', 'payments'],
        'complete' => false,
    ]);
});

it('says which of the three things a write did', function (): void {
    expect(Present::outcome(Outcome::recorded(7)))
        ->toBe(['recording' => 'recorded', 'reason' => null, 'id' => 7, 'count' => 1]);

    expect(Present::outcome(Outcome::alreadyRecorded(7)))
        ->toBe(['recording' => 'already_recorded', 'reason' => null, 'id' => 7, 'count' => 0]);

    expect(Present::outcome(Outcome::refused(RefusalReason::NotResolved)))
        ->toBe(['recording' => 'refused', 'reason' => 'not_resolved', 'id' => null, 'count' => 0]);
});

it('never publishes the merchant back in a participant record', function (): void {
    $record = (new ExportParticipantRecord())('merchant-a', 'person-1');

    expect(array_keys(Present::participantRecord($record)))->toBe(['participant_ref', 'conversations']);
});

it('reindexes a collection so a filtered list is still a JSON array', function (): void {
    expect(Present::collection([1 => ['a' => 1], 3 => ['b' => 2]]))->toBe(['data' => [['a' => 1], ['b' => 2]]]);
});
