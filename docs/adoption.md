# Adopting the customer service workspace API

## Install

The domain package is not on Packagist yet, so the repository entry is part of the requirement and
carries that information:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-customer-service-workspace"},
        {"type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-customer-service-workspace-api"}
    ]
}
```

```
composer require liberusoftware/ecommerce-customer-service-workspace-api:^0.1
```

Installing boots nothing. `extra.laravel.providers` is absent on purpose: the module manager
registers the provider when `ecommerce-customer-service-workspace-api` is named in
`MODULES_ENABLED`, and the domain package is registered the same way.

```
php artisan vendor:publish --tag=customer-service-workspace-api-config
```

## What the host must wire

### 1. A middleware that resolves the request

The participant routes have no credential. Their merchant, and the person making the request, come
from the request's attribute bag — which nothing a client sends can reach. The host already performs
this resolution: `ResolveChannel` turns the hostname into a `Channel`, a `Store` and a `Team`.

```php
$request->attributes->set('tenant_id', (string) $channel->store->team_id);
$request->attributes->set('participant_ref', (string) Auth::id());   // or a guest token of the host's own
```

Both attribute names are configurable under `customer-service-workspace-api.participant`. Neither is
defaulted: a request whose merchant was not resolved answers **503 `no_merchant_resolved`**, and
opening a conversation for nobody answers **503 `no_participant_resolved`**. A surface that guessed a
merchant would file one storefront's conversations into another business.

Guests: the host mints the guest reference, not this package. It is the value erasure and export are
keyed on, so it has to be stable for as long as the host wants a person to be one person. Minting one
here would be a third opaque identifier nobody could use, which is the wave's shaping fault repeated.

### 2. A guard whose user answers `tokenCan()`

Every agent route requires an authenticated actor whose model exposes `tokenCan(string): bool` and
carries the merchant on the attribute named by `customer-service-workspace-api.actor.tenant_attribute`
(`team_id` by default). Sanctum's `PersonalAccessToken` satisfies the first; Jetstream's `Team`
membership supplies the second.

Four abilities, kept apart on purpose:

| | |
|---|---|
| `customer-service:read` | The queue, a conversation, a transcript, notes, timelines, measurement |
| `customer-service:work` | Taking, resolving, abandoning, writing a line or a note, read receipts |
| `customer-service:act` | Asking another module to refund, cancel or reship — and reading what it said |
| `customer-service:privacy` | Export, erasure, retention |

A token that may answer a customer must not thereby be able to move their money or erase them.

### 3. The rate limits, if the defaults are wrong for you

```php
'throttle' => ['agent' => '120,1', 'participant' => '30,1', 'open' => '5,1'],
```

`attempts,minutes`. They are not empty and must not be emptied: the host's chat left the two reads
that return a transcript and a customer's email under no limit at all, and the domain counts no
requests because it has no notion of a caller. Raising a band is a decision; removing one is
reopening the fault.

### 4. The seams, if you want a timeline or safe actions

Both live in the **domain** package's config, not this one. Unbound is a supported state:

```php
'customer-service-workspace.seams.timeline' => ['orders' => OrdersTimeline::class, 'payments' => null, ...],
'customer-service-workspace.seams.actions' => null,
```

An unbound timeline source is named in `skipped` and the answer says it is incomplete. An unbound
action gateway records the request and answers 503 with the record beside the error. Neither is a
failure and neither substitutes a zero.

## The claim

`POST /participant/conversations` is the only response that carries the claim. Store it wherever the
storefront stores a shopper's own state, and send it back as `X-Participant-Claim`. There is no
recovery path: the module keeps only its hash and re-issues nothing. A customer who loses the claim
opens a new conversation.

Do not put it in a URL. The host's chat did, and its only secret reached every access log and every
`Referer` header. Do not put it in a cookie either: a cookie is ambient authority the browser attaches
to requests nobody wrote code for, and that is what the host's `chat_session_id` was.

## What the host deletes

| Host code | Why it is not adopted |
|---|---|
| `app/Http/Controllers/ChatController.php` and `routes/web.php:225–233` | Replaced whole. Five of its six endpoints authorised; `getBySession` did not, and returned a transcript with `customer_name` and `customer_email` under no throttle. |
| `app/Services/ChatService.php` | The domain package owns it. Its queue positions, counters and silent no-ops are the faults the extraction exists to answer. |
| `app/Models/ChatConversation.php`, `ChatMessage.php`, `ChatAnalytics.php` | The domain owns these tables under `csw_`. The analytics table has no successor: measurement is subtraction over timestamps, not counters. |
| `app/Livewire/ChatWidget.php` and `resources/views/livewire/chat-widget.blade.php` | Presentation. The `-livewire` package rebuilds it against the participant routes here. Take none of its session handling. |
| `app/Filament/Admin/Pages/ChatAgentDashboard.php` | A second implementation of assignment with different behaviour from the service's. The `-filament` package rebuilds it against the domain's one path. |
| `tests/Feature/ChatIdorTest.php:55` | `test_admin_can_read_any_conversation()` asserts that any admin is an agent on every merchant. It is a test of the fault, not a contract. |

`order_status_history` is **not** deleted or moved. It is the orders module's state-machine audit row
and it is in use. Only `order_notes` and `order_events` come here, as this module's notes and
timeline.

## Migration

There is none. The host's chat tables cannot be migrated into `csw_` without inventing the values the
faults destroyed: a conversation's participant claim was never minted, the queue positions were
global rather than per-merchant, and `response_time_seconds` measured assignment under a name that
promised a response. Conversations open at cutover are answered on the old stack until they close.
Say so in the release note rather than shipping a migration that fabricates a claim.
