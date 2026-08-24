<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\FakeTimelineSource;

it('names every source nobody bound rather than answering an empty timeline', function (): void {
    $this->actingAs(agent())
        ->getJson(api('timelines/order/ORD-1'))
        ->assertOk()
        ->assertJsonPath('data.entries', [])
        ->assertJsonPath('data.answered', ['notes'])
        ->assertJsonPath('data.skipped', ['orders', 'payments', 'shipments', 'returns'])
        ->assertJsonPath('data.complete', false);
});

it('names the ones that answered and the ones that did not, in the same answer', function (): void {
    bindTimeline(new FakeTimelineSource('orders'));

    $this->actingAs(agent())
        ->getJson(api('timelines/order/ORD-1'))
        ->assertOk()
        ->assertJsonPath('data.answered', ['notes', 'orders'])
        ->assertJsonPath('data.skipped', ['payments', 'shipments', 'returns'])
        ->assertJsonPath('data.entries.0.source', 'orders')
        ->assertJsonPath('data.entries.0.kind', 'placed')
        ->assertJsonPath('data.entries.0.payload.total', 1999)
        ->assertJsonPath('data.complete', false);
});

it('names a source that failed rather than counting it as having answered nothing', function (): void {
    bindTimeline(new FakeTimelineSource('orders', throws: true), new FakeTimelineSource('payments'));

    $this->actingAs(agent())
        ->getJson(api('timelines/order/ORD-1'))
        ->assertOk()
        ->assertJsonPath('data.answered', ['notes', 'payments'])
        ->assertJsonPath('data.skipped', ['orders', 'shipments', 'returns']);
});

it('carries the module own notes into the timeline', function (): void {
    $agent = agent();

    $this->actingAs($agent)
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'internal', 'body' => 'Chased the courier.'])
        ->assertStatus(201);

    bindTimeline(new FakeTimelineSource('orders'), new FakeTimelineSource('payments'), new FakeTimelineSource('shipments'), new FakeTimelineSource('returns'));

    $this->actingAs($agent)
        ->getJson(api('timelines/order/ORD-1'))
        ->assertOk()
        ->assertJsonPath('data.skipped', [])
        ->assertJsonPath('data.complete', true)
        ->assertJsonCount(5, 'data.entries')
        ->assertJsonPath('data.entries.4.source', 'notes')
        ->assertJsonPath('data.entries.4.payload.body', 'Chased the courier.');
});
