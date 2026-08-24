# Runbook

## Every participant request answers 503 `no_merchant_resolved`

The host's middleware is not setting the request attribute. Check it runs on the routes this package
mounts, and that it uses `$request->attributes->set(...)` rather than `$request->merge(...)` —
`merge` writes into the input bag, which is where a client could reach.

There is no fallback and there will not be one. A default merchant files one storefront's
conversations into another business.

## `POST /participant/conversations` answers 503 `no_participant_resolved`

The merchant resolved and the person did not. The host names the person: an account id for a signed-in
shopper, a guest token of its own for anybody else. This package mints nothing, because the value has
to be stable for as long as the host wants a person to be one person — it is what export and erasure
are keyed on.

## A customer says they cannot get back to their conversation

Ask what they are sending as `X-Participant-Claim`. Then stop: the module keeps only the hash and has
no path that re-issues a claim. There is no support action that recovers one, by design — a claim that
could be re-served on request is a claim anybody can ask for. They open a new conversation.

If **every** resume is failing, the likely cause is the storefront dropping the header rather than the
claim being wrong. A missing claim and a wrong claim are the same 404 on purpose, so the response will
not tell you which; check the client.

## Everything answers 429

A band is spent. `customer-service-workspace-api.throttle` has three; the response carries
`Retry-After`. Raise the band in config if the traffic is real.

Do not empty a band. That reopens host fault 16 — an unthrottled read returning a transcript and a
customer's email — and this package has no other limit behind it.

## An action request answers 503 `no_gateway_bound`

Expected in a deployment that has bound no `ActionGateway`. **The request was written**: the response
carries `data.request` with `state: requested`. It is not lost and it is not sent. Bind a gateway in
the domain package's config (`customer-service-workspace.seams.actions`) and the next request
transmits; the outstanding ones do not retry themselves — nothing here schedules anything.

502 `gateway_unreachable` is the same shape with a bound gateway that threw. 409 `gateway_refused` is
the owning module saying no, and `data.request.message` carries what it said.

## A timeline is short

Read `skipped`. Every source that was not asked is named there and `complete` is `false`. A source is
named when it is unbound in this deployment or when it threw; the two are not distinguished on the
wire, because to a caller they are the same fact — that source did not answer.

A timeline with `answered: ["notes"]` and four skipped sources is a correctly working deployment that
has bound nothing.

## A figure is null

It has not happened yet. `wait_seconds` is null until somebody reached the conversation,
`first_reply_seconds` until an agent said something, `resolution_seconds` until it closed. Null is
never rendered as zero anywhere in this surface, and a mean over nothing is null rather than zero.

`queue_position: null` means the conversation is not in the queue. It does not mean first.

## Two callers, one 404

Another merchant's reference, a reference that does not exist and a claim that does not match are one
response with one status and one body. There is no way to tell them apart from outside, which is the
point: a refusal that differed would publish the row. From inside, the merchant on the credential and
the conversation's `tenant_id` are what to compare.

## A new refusal reason arrived from the domain

`Unit\FailureMapTest` fails, in both directions, before anything reaches a caller. Add the case to
`Http\Failure::REFUSALS` with a status, a code and a `resubmittable`, document it in
`resources/openapi/openapi.json`, and the parity test will tell you if the two disagree.

## The OpenAPI document and the router disagree

`Unit\OpenApiParityTest` is the gate and it runs in both directions: an operation with no route and a
route with no operation both fail, as does an operation documenting an ability the controller does not
enforce. The document is not generated — edit `resources/openapi/openapi.json` and let the test decide
whether you are done.
