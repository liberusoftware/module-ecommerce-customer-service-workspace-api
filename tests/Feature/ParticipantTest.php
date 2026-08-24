<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantController;

it('opens a conversation and serves the claim once', function (): void {
    $response = $this->withHeaders(storefront())
        ->postJson(api('participant/conversations'), ['channel' => 'chat', 'name' => 'A Customer', 'email' => 'customer@example.test'])
        ->assertStatus(201);

    $reference = (string) $response->json('data.reference');
    $claim = (string) $response->json('data.claim');

    expect(array_keys((array) $response->json('data')))->toBe(['reference', 'claim'])
        ->and($claim)->not->toBe('')
        ->and($claim)->not->toBe($reference);

    // Every later response about this conversation, and none of them carry it.
    $resumed = $this->withHeaders(claimed($claim))->getJson(api("participant/conversations/{$reference}"))->assertOk();

    expect((string) $resumed->getContent())->not->toContain($claim);
});

it('mints a new conversation on every open, because a retry cannot be served a one-time secret', function (): void {
    $payload = ['channel' => 'chat'];

    $first = $this->withHeaders(storefront())->postJson(api('participant/conversations'), $payload)->assertStatus(201);
    $second = $this->withHeaders(storefront())->postJson(api('participant/conversations'), $payload)->assertStatus(201);

    expect($first->json('data.reference'))->not->toBe($second->json('data.reference'))
        ->and($first->json('data.claim'))->not->toBe($second->json('data.claim'));
});

it('resumes a conversation with the claim it issued', function (): void {
    $opened = openConversation();

    $response = $this->withHeaders(claimed($opened->claim))
        ->getJson(api("participant/conversations/{$opened->reference}"))
        ->assertOk();

    expect(array_keys((array) $response->json('data')))
        ->toBe(['reference', 'channel', 'state', 'queued_at', 'resolved_at', 'abandoned_at', 'queue_position']);

    $response->assertJsonPath('data.queue_position', 1);
});

it('answers a missing claim, a wrong claim and a wrong merchant with one refusal', function (): void {
    $opened = openConversation();
    $bodies = [];

    foreach ([
        storefront(null),
        claimed('not-the-claim'),
        claimed($opened->claim, 'merchant-b'),
    ] as $headers) {
        $response = $this->withHeaders($headers)
            ->getJson(api("participant/conversations/{$opened->reference}"))
            ->assertStatus(404);

        $bodies[] = (string) $response->getContent();
    }

    expect(array_unique($bodies))->toHaveCount(1);
});

it('shows the customer what was said and not which account said it', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/messages"), ['body' => 'On its way.'])->assertStatus(201);

    $response = $this->withHeaders(claimed($opened->claim))
        ->getJson(api("participant/conversations/{$opened->reference}/messages"))
        ->assertOk();

    expect(array_keys((array) $response->json('data.0')))
        ->toBe(['sequence', 'author', 'body', 'sent_at', 'redacted']);
});

it('lets the customer say something', function (): void {
    $opened = openConversation();

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/messages"), ['body' => 'Where is my order?'])
        ->assertStatus(201)
        ->assertJsonPath('data.recording', 'recorded');
});

it('refuses a rating on a conversation that is not resolved', function (): void {
    $opened = openConversation();

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 5])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'not_resolved')
        ->assertJsonPath('error.resubmittable', true);
});

it('records a rating once and says so the second time', function (): void {
    $opened = openConversation();
    settled(agent(), $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 4, 'feedback' => 'Quick.'])
        ->assertStatus(201)
        ->assertJsonPath('data.recording', 'recorded');

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 1])
        ->assertOk()
        ->assertJsonPath('data.recording', 'already_recorded');
});

it('leaves the range a score may take to the domain', function (): void {
    $opened = openConversation();
    settled(agent(), $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 9])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'score_out_of_range')
        ->assertJsonPath('error.resubmittable', true);
});

it('refuses a score that is not a number at all', function (): void {
    $opened = openConversation();
    settled(agent(), $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 'five'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('reads the claim from a header and never from the path', function (): void {
    $opened = openConversation();

    expect(ParticipantController::CLAIM_HEADER)->toBe('X-Participant-Claim');

    // The claim as a path segment is a reference that does not exist.
    $this->withHeaders(storefront(null))
        ->getJson(api("participant/conversations/{$opened->claim}"))
        ->assertStatus(404);
});
