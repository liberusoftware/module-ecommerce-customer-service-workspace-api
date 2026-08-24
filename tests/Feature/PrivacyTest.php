<?php

declare(strict_types=1);

it('exports what is held about one person', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/messages"), ['body' => 'Where is my order?'])
        ->assertStatus(201);

    $response = $this->actingAs($agent)
        ->postJson(api('participant-records'), ['participant_ref' => 'person-1'])
        ->assertOk()
        ->assertJsonPath('data.participant_ref', 'person-1');

    expect($response->json('data.conversations'))->toHaveCount(1)
        ->and($response->json('data.conversations.0.participant_email'))->toBe('customer@example.test')
        ->and($response->json('data.conversations.0.messages.0.body'))->toBe('Where is my order?')
        ->and(array_keys((array) $response->json('data')))->toBe(['participant_ref', 'conversations']);
});

it('answers an empty record for somebody it holds nothing about', function (): void {
    $this->actingAs(agent())
        ->postJson(api('participant-records'), ['participant_ref' => 'nobody'])
        ->assertOk()
        ->assertJsonPath('data.conversations', []);
});

it('forgets a person and leaves the conversation and its timings standing', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api('erasures'), ['participant_ref' => 'person-1'])
        ->assertOk()
        ->assertJsonPath('data.recording', 'recorded')
        ->assertJsonPath('data.count', 1);

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}"))
        ->assertOk()
        ->assertJsonPath('data.participant_name', null)
        ->assertJsonPath('data.participant_email', null)
        ->assertJsonPath('data.state', 'resolved');

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/messages"))
        ->assertOk()
        ->assertJsonPath('data.0.body', 'redacted')
        ->assertJsonPath('data.0.redacted', true);

    $measurement = $this->actingAs($agent)->getJson(api("conversations/{$opened->reference}/measurement"))->assertOk();

    expect($measurement->json('data.resolution_seconds'))->not->toBeNull();
});

it('forgets nobody and says so, rather than reporting a failure', function (): void {
    $this->actingAs(agent())
        ->postJson(api('erasures'), ['participant_ref' => 'nobody'])
        ->assertOk()
        ->assertJsonPath('data.recording', 'recorded')
        ->assertJsonPath('data.count', 0);
});

it('refuses a retention run this deployment never configured', function (): void {
    $this->actingAs(agent())
        ->postJson(api('redactions'), [])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'retention_not_configured')
        ->assertJsonPath('error.resubmittable', true);
});

it('refuses a window that is not a window', function (): void {
    $this->actingAs(agent())
        ->postJson(api('redactions'), ['days' => 0])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'retention_not_configured');
});

it('runs a retention window a caller named', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $this->travel(40)->days();

    $this->actingAs($agent)
        ->postJson(api('redactions'), ['days' => 30])
        ->assertOk()
        ->assertJsonPath('data.recording', 'recorded')
        ->assertJsonPath('data.count', 1);

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/messages"))
        ->assertOk()
        ->assertJsonPath('data.0.redacted', true);

    $this->travelBack();
});

it('redacts nothing that is still inside the window', function (): void {
    $agent = agent();
    settled($agent, openConversation());

    $this->actingAs($agent)
        ->postJson(api('redactions'), ['days' => 30])
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});
