<?php

declare(strict_types=1);

it('leaves every figure null while nothing has happened', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->getJson(api("conversations/{$opened->reference}/measurement"))
        ->assertOk()
        ->assertJsonPath('data.wait_seconds', null)
        ->assertJsonPath('data.first_reply_seconds', null)
        ->assertJsonPath('data.resolution_seconds', null)
        ->assertJsonPath('data.rating', null)
        ->assertJsonPath('data.abandoned', false)
        ->assertJsonPath('data.measured', false);
});

it('measures a resolved conversation from arrival rather than from assignment', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $response = $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/measurement"))
        ->assertOk()
        ->assertJsonPath('data.measured', true)
        ->assertJsonPath('data.abandoned', false);

    expect($response->json('data.resolution_seconds'))->toBeGreaterThanOrEqual((int) $response->json('data.wait_seconds'))
        ->and($response->json('data.first_reply_seconds'))->not->toBeNull();
});

it('measures a conversation abandoned in the queue, which the host recorded as nothing', function (): void {
    $agent = agent();
    $opened = openConversation();

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/abandonment"))->assertOk();

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/measurement"))
        ->assertOk()
        ->assertJsonPath('data.abandoned', true)
        ->assertJsonPath('data.measured', true);
});

it('carries the rating into the measurement once it exists', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/rating"), ['score' => 5])
        ->assertStatus(201);

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/measurement"))
        ->assertOk()
        ->assertJsonPath('data.rating', 5);
});

it('answers a mean over nothing with null rather than with zero', function (): void {
    $this->actingAs(agent())
        ->getJson(api('measurement'))
        ->assertOk()
        ->assertJsonPath('data.measured', 0)
        ->assertJsonPath('data.unmeasured', 0)
        ->assertJsonPath('data.average_wait_seconds', null)
        ->assertJsonPath('data.average_resolution_seconds', null)
        ->assertJsonPath('data.average_rating', null)
        ->assertJsonPath('data.rated', 0);
});

it('excludes an open conversation from every mean and counts it', function (): void {
    $agent = agent();
    settled($agent, openConversation('merchant-a', 'person-1'));
    openConversation('merchant-a', 'person-2');

    $response = $this->actingAs($agent)->getJson(api('measurement'))->assertOk()
        ->assertJsonPath('data.measured', 1)
        ->assertJsonPath('data.unmeasured', 1)
        ->assertJsonPath('data.abandoned', 0);

    expect($response->json('data.average_resolution_seconds'))->not->toBeNull();
});
