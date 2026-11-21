# OnePage -> OpenAI Responses: structured-copy candidate (NO-GO)

21/11/2026 UTC. Child of AI Agents #489 (HEAD 3f9ef347e51fe419e75d6582bf7630a51fe371dd).

## Implemented

- New pure PHP boundary, without an HTTP transport or secrets, for a future private provider adapter.
- The request builder reuses the exact versioned synthetic task validator of the independent AI Agents #489 worker. It creates a bounded Responses request with strict JSON Schema and only the five sites.ai-copy.v1 fields: schemaVersion, headline, description, callToAction, benefits.
- Model identifier must be supplied by a future authorized owner. There is no model or account default, no request to any API, no retry or loop, no tool use, no HTML generation, and no display of task/customer metadata.
- The recorded-response validator refuses incomplete/errors, unexpected model, multiple output messages, refusals, annotations, invalid usage, extra authority, v4, duplicate-key/noncanonical JSON, invisible controls, HTML and oversized copy. It only returns non-secret response reference, the synthetic task/briefing digests and exact accepted copy with digest.
- Offline isolated PHP 8.3/8.4 workflow tests both positive and negative fake envelopes. The fixture model is named model-fixture-only, never a production model.

## NOT proved

No real OpenAI request or network access; no provisioned OnePage-only identity, approved model, credential or outbound policy. No wired production worker; the current executable still advertises imagine.onepage-agent.mock.v1, and silently changing it would break Sites #578/#579. No real Portal immutable briefing approved by buyer/reviewer, mTLS, authenticated buyer preview, final acceptance, or publication.

Future live rehearsal requires independent non-secret proof of a scoped, privately authorized worker with existing credential and pre-approved model/egress. The worker must bind the real response to exact task/revision, return a versioned receipt and let Sites verify its established schema/render pipeline. This pure candidate cannot grant consent, publication, payments or delivery.

Portal #976 remains a fail-closed contract without an operational transacted source. Sites #38 and all payment/publishing gates remain NO-GO. Sites #541/#542 are historical; Landing Page has a separate evidence chain and receives no authority from this work.

Documentation reference: https://developers.openai.com/api/reference/resources/responses/methods/create
