<?php

declare(strict_types=1);

it('writes an internal note and lists it', function (): void {
    $agent = agent();

    $this->actingAs($agent)
        ->postJson(api('notes/conversation/csw_1'), ['visibility' => 'internal', 'body' => 'Customer sounded upset.'])
        ->assertStatus(201)
        ->assertJsonPath('data.recording', 'recorded');

    $this->actingAs($agent)
        ->getJson(api('notes/conversation/csw_1'))
        ->assertOk()
        ->assertJsonPath('data.0.visibility', 'internal')
        ->assertJsonPath('data.0.author_ref', (string) $agent->getKey())
        ->assertJsonPath('data.0.subject_kind', 'conversation')
        ->assertJsonPath('data.0.redacted', false);
});

it('publishes a customer-visible note, which is a publication and not an edit away', function (): void {
    $this->actingAs(agent())
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'customer_visible', 'body' => 'Your postage is refunded.'])
        ->assertStatus(201);

    $this->actingAs(agent())
        ->getJson(api('notes/order/ORD-1'))
        ->assertOk()
        ->assertJsonPath('data.0.visibility', 'customer_visible');
});

it('refuses a system note, because no agent action produces one', function (): void {
    $this->actingAs(agent())
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'system', 'body' => 'Pretending to be the module.'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'author_may_not_write')
        ->assertJsonPath('error.resubmittable', false);
});

it('lets the domain decide whether an empty note is a note', function (): void {
    $this->actingAs(agent())
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'internal', 'body' => ''])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_body');
});

it('refuses a visibility the domain does not define', function (): void {
    $this->actingAs(agent())
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'secret', 'body' => 'A note.'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('answers an empty list for a subject nobody has annotated', function (): void {
    $this->actingAs(agent())
        ->getJson(api('notes/order/ORD-NOTHING'))
        ->assertOk()
        ->assertJsonPath('data', []);
});
