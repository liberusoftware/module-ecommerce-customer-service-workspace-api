<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Controllers\ParticipantController;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Scope;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Http\Throttle;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\CredentiallessActor;
use Liberu\Ecommerce\CustomerServiceWorkspace\Api\Tests\Fixtures\ProbeController;

it('refuses an agent route with no credential at all', function (): void {
    $this->getJson(api('queue'))
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated')
        ->assertJsonPath('error.resubmittable', false);
});

it('refuses a credential that does not carry the ability', function (): void {
    $this->actingAs(actor([Scope::READ]))
        ->postJson(api('erasures'), ['participant_ref' => 'person-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_scope');
});

it('refuses a credential attached to no merchant', function (): void {
    $this->actingAs(actor(Scope::all(), null))
        ->getJson(api('queue'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'actor_has_no_merchant');
});

it('refuses a credential that cannot answer for an ability at all', function (): void {
    $actor = CredentiallessActor::query()->create(['team_id' => 'merchant-a']);

    $this->actingAs($actor)
        ->getJson(api('queue'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_scope');
});

it('refuses a routed action that publishes no ability', function (): void {
    Route::middleware(Throttle::for(Throttle::AGENT))
        ->get(api('probe/unpublished'), [ProbeController::class, 'unpublished']);

    $this->actingAs(agent())
        ->getJson(api('probe/unpublished'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_scope');
});

it('refuses a participant whose merchant the deployment did not resolve', function (): void {
    $opened = openConversation();

    $this->withHeaders([ParticipantController::CLAIM_HEADER => $opened->claim])
        ->getJson(api("participant/conversations/{$opened->reference}"))
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'no_merchant_resolved');
});

it('opens no conversation when the deployment named nobody', function (): void {
    $this->withHeaders(storefront(null))
        ->postJson(api('participant/conversations'), ['channel' => 'chat'])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'no_participant_resolved');
});

it('never reads the merchant from the body', function (): void {
    $mine = openConversation('merchant-a');

    $this->actingAs(agent('merchant-b'))
        ->getJson(api("conversations/{$mine->reference}?team_id=merchant-a"), ['X-Merchant' => 'merchant-a'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});
