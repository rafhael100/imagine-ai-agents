# OnePage synthetic process worker — AI Agents candidate (NO-GO)

An independent CLI process (not an in-process closure) accepts the exact Sites #577 `sites.onepage-agent-task.v1` canonical JSON and returns `sites.onepage-agent-response.v1` carrying `sites.ai-copy.v1` (five fields). It is not a provider integration.

- Sites owns tenant, order, briefing source and version, brand, HTML rendering, consent, review, release and publication; none is granted by this worker.
- Only the fixed fictional copyHints set is accepted. JSON is capped at 2048 bytes; duplicate keys, unexpected fields, real briefing text and wrong versions are refused.
- CI requires `IMAGINE_ONEPAGE_PROCESS_LAB=1`. One stdin task produces a single stdout JSON; no providers, network, retries, keys or new access.
- v4 historical Sites #284 is not substituted for selected v1.
- A separate Sites #577 successor must pin this worker SHA, call the actual process and independently verify digest of rendered HTML via local HTTP.

Authorized OpenAI credits are not proof of isolated credential/egress or approved operational identity. No real calls attempted. Portal #976 still lacks official immutable briefing source, buyer rights confirmation and reviewer revocation. Sites #38 NO-GO; Landing Page separate. No merge, deploy, checkout, DNS or VPS.
