# Ecommerce — Customer Service Workspace API

An HTTP adapter over [`liberusoftware/ecommerce-customer-service-workspace`](https://github.com/liberusoftware/module-ecommerce-customer-service-workspace).
It presents that module and holds no business rules of its own: every decision — who may read a
conversation, whether a move is legal, what a figure means when it is missing — is the domain's, and
this package is the transport.

## What it owns

Transport, and the three things the domain cannot decide for itself.

- **A rate limit on every route.** The domain counts no requests and cannot: it has no notion of a
  caller. The host left the two endpoints that return a transcript and a customer's email
  unthrottled, and this package closes that.
- **A transport for the participant's claim.** The domain issues it once, in plaintext, and stores
  only its hash. It travels in the `X-Participant-Claim` header — never in a path or a query string,
  where the host put its only secret and where it landed in every access log and `Referer`.
- **A classification of every failure.** Four exceptions and all nine refusal reasons map to a
  status, a code and a message in one table, asserted exhaustive by test.

## What it does not own

Anything the domain decides. It computes no duration, derives no queue position, validates no score
range and re-implements no state machine. Where the surface looked like it needed a rule — a score
between one and five, an empty message body — the rule stayed in the domain and the surface passes
the value through so there is one answer rather than two.

It also owns nothing outside the module: orders, payments, shipments and returns arrive through the
domain's unbound seams, and no adapter ships here.

## The fact that shaped it

The host's chat had two session identifiers and neither was the other: the widget minted one and
searched by it, the server minted a different one and stored that. No customer could ever return to
their own conversation. The module answers it with two values doing two jobs — a reference that names
a conversation and a claim that proves standing to read it — and this surface must not reintroduce a
third. It mints nothing. The merchant comes from the credential or from a request attribute the host
resolved; the participant is named by the host; the reference and the claim are the module's.

## What it publishes

| | |
|---|---|
| `GET queue` | The conversations waiting, in arrival order, naming nobody |
| `GET conversations/{reference}` | An agent's working view, with the queue position |
| `GET` and `POST conversations/{reference}/messages` | The transcript, and an agent's line in it |
| `POST conversations/{reference}/{assignment,resolution,abandonment}` | The three moves an agent makes |
| `POST conversations/{reference}/read-receipts` | Marking the other side's lines read |
| `GET conversations/{reference}/measurement`, `GET measurement` | One conversation, and the merchant's service quality |
| `GET` and `POST conversations/{reference}/action-requests` | Asking another module to act, and what it said |
| `GET` and `POST notes/{subject_kind}/{subject_ref}` | Notes about a conversation, an order, anything |
| `GET timelines/{subject_kind}/{subject_ref}` | The assembled timeline, naming every source that could not be asked |
| `POST participant-records`, `POST erasures`, `POST redactions` | Export, erasure and retention |
| `POST participant/conversations` | Opening one, and the only response that carries the claim |
| `GET participant/conversations/{reference}` | Resuming one, with the claim |
| `GET` and `POST participant/conversations/{reference}/messages` | The customer's view of the transcript |
| `POST participant/conversations/{reference}/rating` | The rating, once, on a resolved conversation |

Four abilities: `customer-service:read`, `:work`, `:act` and `:privacy`. Asking another module to
refund or cancel is its own ability, and so is erasing somebody.

## Documentation

`docs/adoption.md` for installing and wiring it, `docs/domain.md` for every decision behind the
surface, `docs/runbook.md` for what breaks and what it looks like. `resources/openapi/openapi.json`
is the description, and `Unit\OpenApiParityTest` is what keeps it true.
