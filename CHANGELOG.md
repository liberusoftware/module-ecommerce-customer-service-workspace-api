# Changelog

## 0.1.0

The HTTP surface over `liberusoftware/ecommerce-customer-service-workspace` `0.1.0`.

### The four gaps the domain left open

- **Throttling.** Every route carries a rate limit, configured in three bands: an agent's
  credentialled work, an anonymous participant's reads, and opening a conversation. The host
  throttled four of its six chat endpoints and left open the two that return a transcript and a
  customer's email. The domain has no notion of a caller and cannot close this.
- **The participant's claim.** Served once, in the body of the response that opens a conversation,
  and presented afterwards in the `X-Participant-Claim` header. Never a path segment, never a query
  string, never a cookie: the first two land in access logs and `Referer`, and a cookie is ambient
  authority the browser attaches by itself, which is a second session identifier by another name.
  No response other than the one that opened the conversation can serve it.
- **The failure map.** Four domain exceptions and all nine refusal reasons classified in one table,
  asserted exhaustive in both directions, so a tenth case is a red test rather than a 500.
- **Refusals as facts on the wire.** A skipped timeline source is named; a null service figure stays
  null; a queue position of null means not queued and never first.

### Decisions

- Two actor families, split mechanically: `AgentController` and `ParticipantController` share a base
  that maps failures and nothing else, and a test asserts every route under `participant/` uses the
  second and every other route the first.
- The merchant is never accepted from a path, a query string or a body. An agent's comes from the
  credential, a participant's from a request attribute the host set when it resolved the storefront
  host to a channel. Nothing is defaulted; an unresolved merchant is a 503.
- The host names the participant, as it names the merchant. This package mints no identifier of any
  kind.
- No idempotency key on opening a conversation: serving a retry would mean serving a one-time secret
  twice. Every other write has a natural key the domain already enforces.
- A refused action request answers 4xx or 5xx **with the persisted request beside the error**,
  because the workspace asked and the row exists whatever the owning module said.
- A score's range and an empty message body are the domain's rules. The surface types the input and
  passes it through, so 422 comes back from one place rather than two.
- An agent assigns themselves, from the credential. Routing a conversation to another agent is
  deliberately not shipped.
- The queue listing carries no name and no email.
- A timeline missing a source answers 200 with the source named in `skipped` and `complete: false`,
  not 503: the module's own notes are a real answer, and hiding them would refuse more than the
  seam controls.
