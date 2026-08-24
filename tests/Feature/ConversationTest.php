<?php

declare(strict_types=1);

it('shows an agent the working view with the derived queue position', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->getJson(api("conversations/{$opened->reference}"))
        ->assertOk()
        ->assertJsonPath('data.reference', $opened->reference)
        ->assertJsonPath('data.state', 'queued')
        ->assertJsonPath('data.participant_email', 'customer@example.test')
        ->assertJsonPath('data.queue_position', 1)
        ->assertJsonPath('data.agent_ref', null);
});

it('names nobody in the queue listing', function (): void {
    openConversation();

    $response = $this->actingAs(agent())->getJson(api('queue'))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and(array_keys((array) $response->json('data.0')))
        ->toBe(['reference', 'channel', 'state', 'queued_at', 'resolved_at', 'abandoned_at']);
});

it('takes a conversation for the credential that asked, and says so a second time', function (): void {
    $agent = agent();
    $opened = openConversation();

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/assignment"))
        ->assertOk()
        ->assertJsonPath('data.recording', 'recorded');

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/assignment"))
        ->assertOk()
        ->assertJsonPath('data.recording', 'already_recorded');

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}"))
        ->assertJsonPath('data.agent_ref', (string) $agent->getKey())
        ->assertJsonPath('data.queue_position', null);
});

it('refuses to reopen a resolved conversation by assigning an agent to it', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/assignment"))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition')
        ->assertJsonPath('error.resubmittable', false);
});

it('refuses an agent line in a conversation nobody has taken', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->postJson(api("conversations/{$opened->reference}/messages"), ['body' => 'Hello.'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'author_may_not_write');
});

it('records an agent line once the conversation is taken', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/messages"), ['body' => 'Looking into it.'])
        ->assertStatus(201)
        ->assertJsonPath('data.recording', 'recorded');

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/messages"))
        ->assertOk()
        ->assertJsonPath('data.0.author', 'agent')
        ->assertJsonPath('data.0.body', 'Looking into it.')
        ->assertJsonPath('data.0.author_ref', (string) $agent->getKey())
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('data.0.redacted', false);
});

it('refuses another line in a closed conversation', function (): void {
    $agent = agent();
    $opened = openConversation();
    settled($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/messages"), ['body' => 'One more thing.'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'conversation_closed');
});

it('lets the domain decide whether an empty line is a message', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/messages"), ['body' => '   '])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_body');
});

it('refuses a request that never named a body at all', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->postJson(api("conversations/{$opened->reference}/messages"), [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.fields.body.0', 'The body field must be present.');
});

it('marks the customer lines read and says how many', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->withHeaders(claimed($opened->claim))
        ->postJson(api("participant/conversations/{$opened->reference}/messages"), ['body' => 'Where is my order?'])
        ->assertStatus(201);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/read-receipts"))
        ->assertOk()
        ->assertJsonPath('data.recording', 'recorded')
        ->assertJsonPath('data.count', 1);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/read-receipts"))
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

it('resolves once and says so the second time', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/resolution"))
        ->assertOk()->assertJsonPath('data.recording', 'recorded');

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/resolution"))
        ->assertOk()->assertJsonPath('data.recording', 'already_recorded');
});

it('refuses to resolve a conversation nobody has taken', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->postJson(api("conversations/{$opened->reference}/resolution"))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition');
});

it('abandons a conversation in the queue, and says so the second time', function (): void {
    $agent = agent();
    $opened = openConversation();

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/abandonment"))
        ->assertOk()->assertJsonPath('data.recording', 'recorded');

    $this->actingAs($agent)->postJson(api("conversations/{$opened->reference}/abandonment"))
        ->assertOk()->assertJsonPath('data.recording', 'already_recorded');
});

it('refuses to abandon a conversation an agent has taken', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/abandonment"))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition');
});

it('answers a reference that does not exist the way it answers one it does not hold', function (): void {
    $this->actingAs(agent())
        ->getJson(api('conversations/csw_nothing'))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found')
        ->assertJsonPath('error.message', 'No such record exists here.');
});
