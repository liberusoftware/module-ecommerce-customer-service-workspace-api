<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | The group's middleware defaults to `[]` and never to null. An empty stack
    | is a host that has not opted in to a middleware yet; a null stack is
    | Laravel silently substituting one, which is an opt-out nobody wrote down.
    |
    | The rate limits below are not part of that stack and are not empty. The
    | host returned a transcript and a customer's email from an unthrottled GET;
    | the domain counts no requests and cannot, so the limit is this package's.
    |
    */

    'route' => [
        'prefix' => 'api/customer-service',
        'middleware' => [],
        'domain' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | `attempts,minutes`, as Laravel's throttle takes them. Three, because three
    | different things are being protected: an agent's credentialled work, an
    | anonymous participant reading personal data, and opening a conversation,
    | which mints a row in the queue and a secret that is served once.
    |
    */

    'throttle' => [
        'agent' => '120,1',
        'participant' => '30,1',
        'open' => '5,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor
    |--------------------------------------------------------------------------
    |
    | An agent's merchant is read from the credential. A participant has none,
    | so theirs is read from a request attribute the host sets when it resolves
    | the storefront host to a channel — the same resolution the host already
    | performs. Nothing is defaulted: an attribute the host did not set is a
    | deployment that has not wired this surface, and every participant route
    | refuses rather than guessing a merchant.
    |
    | `participant_ref` names an attribute too, and opening a conversation needs
    | it. The host knows who the shopper is — an account, or a guest token in its
    | own session — and naming them is its job, as naming the merchant is. This
    | package mints no third identifier.
    |
    */

    'actor' => [
        'tenant_attribute' => 'team_id',
    ],

    'participant' => [
        'tenant_attribute' => 'tenant_id',
        'ref_attribute' => 'participant_ref',
    ],

];
