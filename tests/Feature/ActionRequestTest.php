<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\FakeActionGateway;

function askFor(string $reference, array $overrides = []): array
{
    return array_merge([
        'request_ref' => 'req-1',
        'kind' => 'refund',
        'target_ref' => 'ORD-1',
        'payload' => ['minor' => 1999],
    ], $overrides);
}

it('records the request and refuses the transmission when nothing is bound', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference))
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'no_gateway_bound')
        ->assertJsonPath('error.resubmittable', true)
        ->assertJsonPath('data.request.request_ref', 'req-1')
        ->assertJsonPath('data.request.state', 'requested')
        ->assertJsonPath('data.request.settled_at', null);
});

it('confirms a request the owning module accepted', function (): void {
    bindGateway();
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference))
        ->assertStatus(201)
        ->assertJsonPath('data.recording', 'recorded')
        ->assertJsonPath('data.request.state', 'confirmed')
        ->assertJsonPath('data.request.remote_ref', 'remote-1')
        ->assertJsonPath('data.request.agent_ref', (string) $agent->getKey());

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/action-requests"))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.state', 'confirmed');
});

it('carries the record beside the error when the owning module said no', function (): void {
    bindGateway(new FakeActionGateway(accepted: false));
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'gateway_refused')
        ->assertJsonPath('error.resubmittable', false)
        ->assertJsonPath('data.request.state', 'refused')
        ->assertJsonPath('data.request.message', 'that order has shipped');
});

it('carries the record beside the error when the owning module could not be reached', function (): void {
    bindGateway(new FakeActionGateway(throws: true));
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference))
        ->assertStatus(502)
        ->assertJsonPath('error.code', 'gateway_unreachable')
        ->assertJsonPath('data.request.state', 'requested');
});

it('answers a replay with the record that exists rather than authorising a second act', function (): void {
    bindGateway();
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference))
        ->assertStatus(201);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), askFor($opened->reference, ['target_ref' => 'ORD-2']))
        ->assertOk()
        ->assertJsonPath('data.recording', 'already_recorded')
        ->assertJsonPath('data.request.target_ref', 'ORD-1');

    $this->actingAs($agent)
        ->getJson(api("conversations/{$opened->reference}/action-requests"))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('refuses a request naming no reference of its own', function (): void {
    $opened = openConversation();

    $this->actingAs(agent())
        ->postJson(api("conversations/{$opened->reference}/action-requests"), ['kind' => 'refund', 'target_ref' => 'ORD-1'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('accepts a request carrying no payload at all', function (): void {
    $agent = agent();
    $opened = openConversation();
    takenBy($agent, $opened);

    $this->actingAs($agent)
        ->postJson(api("conversations/{$opened->reference}/action-requests"), ['request_ref' => 'req-2', 'kind' => 'cancel', 'target_ref' => 'ORD-9'])
        ->assertStatus(503)
        ->assertJsonPath('data.request.kind', 'cancel');
});
