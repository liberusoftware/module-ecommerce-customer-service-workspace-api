# What this surface decides, and what it refuses to decide

The domain package is the authority. This document records the decisions transport had to make
anyway, including the ones rejected.

## The four gaps the domain left open

### 1. Throttling, which is nobody else's

The domain has no notion of a caller and cannot count requests. Host fault 16: `routes/web.php:227–232`
throttled `start`, `message`, `close` and `rating`, and left `getMessages` and `getBySession` — the
two that return the transcript and the customer's email — with no limit at all.

**Every route here carries a limit**, not a chosen subset. A list of which routes deserve one is a
list that falls behind the next route somebody adds. Three bands, because three different things are
being protected:

| Band | Default | Why |
|---|---|---|
| `agent` | `120,1` | Credentialled work. The caller is known and revocable. |
| `participant` | `30,1` | Anonymous reads of personal data. The two the host left open. |
| `open` | `5,1` | Opening mints a queue row and a one-time secret. The host made a new queued conversation on every page load and stranded the one before it; a limit is the cheap half of not doing that again. |

`Http\Throttle` resolves the band from config and returns `ThrottleRequests::class.':'.$limit`, the
class rather than the `throttle` alias, so the middleware resolves in a host that has not registered
Laravel's default aliases. `Unit\RoutingTest` asserts every registered route carries exactly one.

**Rejected:** named rate limiters (`RateLimiter::for`). They would make the package depend on a host
having declared them, and an undeclared limiter throttles nothing — a silent failure with the shape of
the fault being closed.

### 2. The claim's transport

The domain issues the claim once, in plaintext, in `ConversationOpened`, and stores only its hash. It
has no path that re-issues one.

**Chosen: a request header, `X-Participant-Claim`.** Served in exactly one response — the 201 from
`POST /participant/conversations` — and never in another. `Unit\PresentTest` asserts the claim appears
in one presenter method and `Feature\ParticipantTest` asserts the resume response does not contain it.

- **Not a path segment.** That is host fault 3 exactly: a `GET` whose only secret was in the path, so
  it landed in every access log and every `Referer`. `Unit\RoutingTest` asserts no route names a claim
  parameter.
- **Not a cookie, signed or otherwise.** A cookie is ambient authority the browser attaches to
  requests nobody wrote, it needs the host to have opted into cookie encryption, and it is a second
  session-shaped value living beside the conversation's reference. The wave's shaping fact is what
  happens when two values both claim to identify a conversation. A header is sent deliberately, by
  code, on the requests that need it.

**No idempotency key on opening.** Serving a retry would mean serving a one-time secret twice, and a
secret that can be served twice proves nothing. `Feature\ParticipantTest` asserts two opens give two
conversations and two claims. Every other write already has a key the database enforces: the
conversation's reference, a rating one-to-one with a resolved conversation, and an action request's
`request_ref`, unique per merchant.

### 3. One failure table, asserted exhaustive

`Http\Failure` holds two maps. Four domain exceptions, and all nine `RefusalReason` cases.
`Unit\FailureMapTest` asserts both directions — nothing unmapped, nothing mapped that the domain does
not publish — so a tenth case added upstream is a red test rather than a 500. The abstract base is
never mapped: mapping it would swallow every exception added later.

| Reason | Status | Code | Resubmittable |
|---|---|---|---|
| `illegal_transition` | 409 | `illegal_transition` | no |
| `conversation_closed` | 409 | `conversation_closed` | no |
| `not_resolved` | 409 | `not_resolved` | yes |
| `score_out_of_range` | 422 | `score_out_of_range` | yes |
| `author_may_not_write` | 409 | `author_may_not_write` | no |
| `gateway_unbound` | 503 | `no_gateway_bound` | yes |
| `gateway_unreachable` | 502 | `gateway_unreachable` | yes |
| `gateway_refused` | 409 | `gateway_refused` | no |
| `retention_not_configured` | 503 | `retention_not_configured` | yes |

The three gateway reasons are three statuses because they are three remedies: bind an adapter, wait
for the owning module, or stop asking. Only the last is spent.

All nine are reachable from a shipped route. Nothing here is a lying constraint.

### 4. A refusal is a fact, and the wire carries it

- **`Timeline::$skipped` renders as names.** `{"entries": [...], "answered": ["notes"], "skipped":
  ["orders", "payments", "shipments", "returns"], "complete": false}`. Never an entry list that looks
  complete, never a count.
- **The three `ServiceMeasurement` figures stay null.** A zero there reads as instant service, which
  is the opposite of what null means. The same for every mean in the summary and for `average_rating`.
- **`QueuePosition` null means not queued.** Not zero, and not first.
- **A refused action request answers with the record beside the error.** The domain persists the
  request before transmitting it, so 409, 502 and 503 all carry `data.request`. Reading the status
  alone would say nothing was written, which is the opposite of what happened.

## Decisions transport made

**Two actor families, split mechanically.** `AgentController` reads the merchant and the agent
reference from the credential. `ParticipantController` reads the merchant from a request attribute
and the claim from a header, and publishes no ability. They share only a base that maps failures.
`Unit\RoutingTest` asserts every route under `participant/` is served by the second and every other
route by the first — the split is a test, not a review note.

**The merchant is never accepted.** Not from a path, a query string or a body. `Unit\RoutingTest`
greps the concrete controllers for `tenant_id`, `team_id`, `merchant_id`, `store_id` and
`tenant_attribute` and requires every one of them to use `$this->tenantId()`. Mechanical rather than
reviewed.

**An agent assigns themselves.** The agent reference is the credential's identifier and no route names
another agent, so no token can put work on somebody else, and host fault 1 — agency from
`hasRole(['super_admin','admin'])` — has no way back in. Routing a conversation to a colleague is
deliberately **not shipped**: it needs a policy about who may, which is the domain's to publish.

**The queue listing carries no name and no email.** An agent who has not opened a conversation has no
reason to read them. Wave 11 shipped reviewer PII on a listing; this is the same shape.

**Rules stay in the domain, even when transport could restate them.** A score's range is
`score_out_of_range` from the domain, not `between:1,5` in a validator. An empty message body is
`invalid_body` from the domain, not `required`. The validator types the input — `integer`, `string`,
`present` — and the domain decides what the value means, so there is one answer rather than two that
can drift apart. That is why the body rules read `['present', 'nullable', 'string']`: Laravel's
`ConvertEmptyStringsToNull` would otherwise turn an empty line into a validation failure before the
domain ever saw it.

**A note is subject-addressed, not conversation-addressed.** The domain's `Note` takes an opaque
subject, and the addendum places the host's dead `order_notes` here. `GET|POST
notes/{subject_kind}/{subject_ref}` serves a conversation, an order and anything else, and the
timeline is addressed the same way. A conversation-scoped pair of routes would have needed a second
pair the first time somebody annotated an order.

**A partial timeline answers 200, not 503.** The reference `-api` package answers 503 when a
reconciliation could not reach the network, because reaching it was the whole operation. Here the
module's own notes are a real answer that an agent needs, and refusing the request would remove more
than the missing seam controls. The refusal is carried in the body, by name, with `complete: false`.

**Retention is an endpoint.** It could have been left to the host's scheduler calling the domain
action. Shipping it gives `retention_not_configured` a real caller and gives an operator a way to run
the policy once against one merchant, which is what a first run looks like.

## What is deliberately not shipped

- **Routing a conversation to another agent.** See above.
- **A conversation search.** The domain publishes lookup by reference and the queue in arrival order.
  A search endpoint would need query semantics the domain has not decided.
- **Any adapter.** No timeline source and no action gateway ship here, and
  `Feature\ModuleBoundaryRulesTest` asserts the source tree names neither contract.
- **A migration from the host's chat tables.** See `adoption.md`; it would have to fabricate a claim.
