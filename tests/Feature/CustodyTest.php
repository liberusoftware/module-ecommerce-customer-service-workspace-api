<?php

declare(strict_types=1);

/*
 * Two wrong answers must be indistinguishable. Another merchant's reference and
 * one that does not exist are one 404 with one body, everywhere.
 */

it('hides another merchant conversation behind the same refusal everywhere', function (): void {
    $mine = openConversation('merchant-a');
    $other = agent('merchant-b');
    $nothing = 'csw_nothing';

    $reads = [
        'conversations/%s',
        'conversations/%s/messages',
        'conversations/%s/measurement',
        'conversations/%s/action-requests',
    ];

    foreach ($reads as $template) {
        $held = $this->actingAs($other)->getJson(api(sprintf($template, $mine->reference)))->assertStatus(404);
        $absent = $this->actingAs($other)->getJson(api(sprintf($template, $nothing)))->assertStatus(404);

        expect((string) $held->getContent())->toBe((string) $absent->getContent(), $template);
    }

    $writes = [
        'conversations/%s/assignment',
        'conversations/%s/resolution',
        'conversations/%s/abandonment',
        'conversations/%s/read-receipts',
    ];

    foreach ($writes as $template) {
        $held = $this->actingAs($other)->postJson(api(sprintf($template, $mine->reference)))->assertStatus(404);
        $absent = $this->actingAs($other)->postJson(api(sprintf($template, $nothing)))->assertStatus(404);

        expect((string) $held->getContent())->toBe((string) $absent->getContent(), $template);
    }
});

it('refuses a line written into another merchant conversation', function (): void {
    $mine = openConversation('merchant-a');

    $this->actingAs(agent('merchant-b'))
        ->postJson(api("conversations/{$mine->reference}/messages"), ['body' => 'Hello.'])
        ->assertStatus(404);
});

it('keeps one merchant notes out of another merchant list', function (): void {
    $this->actingAs(agent('merchant-a'))
        ->postJson(api('notes/order/ORD-1'), ['visibility' => 'internal', 'body' => 'Refunded the postage.'])
        ->assertStatus(201);

    $this->actingAs(agent('merchant-a'))->getJson(api('notes/order/ORD-1'))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs(agent('merchant-b'))->getJson(api('notes/order/ORD-1'))->assertOk()->assertJsonCount(0, 'data');
});

it('counts a merchant own conversations and nobody else', function (): void {
    openConversation('merchant-a');
    openConversation('merchant-a', 'person-2');
    openConversation('merchant-b');

    $this->actingAs(agent('merchant-a'))->getJson(api('queue'))->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs(agent('merchant-b'))->getJson(api('queue'))->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs(agent('merchant-a'))
        ->getJson(api('measurement'))
        ->assertOk()
        ->assertJsonPath('data.unmeasured', 2);
});

it('holds two merchants apart when both name the same subject', function (): void {
    foreach (['merchant-a', 'merchant-b'] as $merchant) {
        $this->actingAs(agent($merchant))
            ->postJson(api('notes/order/ORD-SHARED'), ['visibility' => 'internal', 'body' => "A note from {$merchant}."])
            ->assertStatus(201);
    }

    $this->actingAs(agent('merchant-a'))
        ->getJson(api('notes/order/ORD-SHARED'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'A note from merchant-a.');
});

it('shows a participant nothing under a merchant that is not theirs', function (): void {
    $opened = openConversation('merchant-a');

    $this->withHeaders(claimed($opened->claim, 'merchant-b'))
        ->getJson(api("participant/conversations/{$opened->reference}/messages"))
        ->assertStatus(404);
});
