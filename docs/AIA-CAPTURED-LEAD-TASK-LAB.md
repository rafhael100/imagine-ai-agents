# Captured CRM lead task — isolated domain extension

This branch extends the RC65 laboratory domain. It does not install n8n,
activate a service, change CRM business data, or authorize production. All
operational gates remain **NO-GO**. The combined Central candidate supplies
the authenticated P19 capture and the pinned native workflow.

## Contracts and hashes

The original `MockN8nOutboxContract` and the independently pinned P7 adapter
remain unchanged. `CapturedLeadTaskContract` defines a separate, canonical
`SYNTHETIC_CRM_LEAD_INGEST_V1` object with these eight ordered fields:

`contract`, `event_ref`, `tenant_ref`, `company_id`, `lead_id`,
`source_payload_hash`, `kind`, `occurred_at`.

The kind is exactly `crm.lead.ingest`, its operation is exactly `lead.ingest`,
and the maximum is 512 bytes. The company is a lowercase UUIDv4; the lead is
a lowercase canonical UUID, including the non-v4 values admitted by capture.
References are synthetic. `occurred_at` is the fresh task preparation time,
not a reconstructed CRM business-event timestamp.

`source_payload_hash` identifies the exact captured HTTP payload bytes.
The existing queue/replay/owner/receipt `payload_hash` identifies the canonical
task. The owner signature therefore binds the source hash transitively while
keeping both meanings distinct. No lead is represented as a mock Run.

## One transaction and the existing queue

`PgCapturedLeadStageLab::stage()` validates the immutable capture, its inbox
parent, its original successful admission audit, and the current company
link. It separately checks P41 mapping, P34 tenant policy and a P44 owner
decision signed specifically for `lead.ingest` and this task hash.

The same transaction consumes the owner grant, inserts the P16 replay,
calls the existing P6 transactional enqueue, and writes one
`aia_lead_ingest_lab.captured_job_sources` association with foreign keys to
capture and P6. The association has no queue state, attempt or lease.
All changes roll back together on conflict, missing proof or late expiry.

No P7 nonce or P42 transport proof is fabricated. A lead with both source
types is rejected. Original capture fingerprints, keys and revisions are
historical evidence and remain unchanged. Expiry of the old transport
envelope does not erase a valid admission or become a new execution grant.

## Current execution fences

The new path uses the server wall clock, including checks after writes and
lock waits. A caller-supplied historical clock cannot refresh a lead task.
The historical P7 clock behavior remains unchanged.

Lease and ACK derive operation and source type from the protected P6 task.
Lead leases require current tenant, mapping, capture and owner evidence.
Lead ACK also requires the existing content-bound signed executor receipt.
The raw P6 enqueue/claim/ACK APIs cannot execute a lead through reduced guards;
raw claims continue to consume legacy events in mixed queues.

The existing P26 native execution ledger and P38 signed ACK evidence are
reused by the combined Central candidate. The capture marker
`CAPTURED_NOT_DISPATCHED` remains a historical admission fact; live execution
state belongs to those existing records.

## Audit and validation

The staging audit understands both closed task contracts. The owner-lineage
audit explicitly verifies P7 provenance or captured-lead provenance and
reports missing/mixed proofs, changed source hashes and orphan lead jobs.
Historical audit validity does not reactivate revoked current authority.

`tools/check_captured_lead_contract.php` exercises canonical boundaries,
duplicate keys, operation separation, UUID normalization, freshness and
the distinction between source and task hashes. The combined CI must also
exercise authenticated capture, staging, lease, real native n8n execution,
signed ACK, revocation, replay, recovery and the existing P7 regressions.
No local PHP runtime execution is claimed by this implementation handoff.
