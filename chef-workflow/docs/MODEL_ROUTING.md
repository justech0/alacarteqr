# Model routing

Research date: 2026-10-13.
Availability must match the model picker in the actual client.
These assignments are project recommendations, not universal model rankings.
No public benchmark proves one model wins every PHP or browser task here.

## Recommended selection

| Work | Client and model | Effort | Reason |
|---|---|---|---|
| Main implementation | Codex, GPT-6.1 Sol | Default or Medium | Keep code ownership and context stable |
| Database, authentication, media, admin catalog, security | Codex, GPT-6.1 Sol | High | Spend more reasoning on consistency and access controls |
| Bounded Chrome checks | Antigravity, Gemini 3.8 Flash | Default or Medium | Use `browser_subagent` DOM and screenshots for observable workflows |
| Difficult visual diagnosis | Antigravity, Gemini 3.1 Pro | Available deeper setting | Escalate an unresolved problem with evidence |
| Exceptional hard security decision | Codex, GPT-6 Astra | Available setting | Optional escalation after a specific blocker |
| Simple repeated documentation edits | Codex, GPT-6 Luna | Default or High | Optional focused task only |

OpenAI recommends GPT-6.1 Sol for complex coding when available.
Its documentation advises starting with the default reasoning setting.
Higher effort can increase time and token use.
Antigravity lists Gemini 3.8 Flash and Gemini 3.1 Pro for Google AI Pro.
Its browser integration can operate local Chrome and capture visual evidence.
The browser capability belongs to the client and tools, not an exclusive model guarantee.

## Client distinction

Use Codex through its available application, CLI, or supported IDE extension.
Use Gemini through Antigravity for the assigned browser reviews.
Antigravity's native model table does not list the Codex product as a model.
GPT-OSS is not the Codex product.
Keep the user's existing working integration when it exposes the required tools.
Do not add a provider proxy merely to match this table.

## Fallback

Choose GPT-6 Sol when GPT-6.1 Sol is unavailable in Codex.
Otherwise record the available coding model and use the same acceptance checks.
Choose another available Gemini Flash when 3.8 Flash is unavailable.
Use Gemini 3.1 Pro when the assigned Flash cannot resolve the documented task.
Keep one implementation owner until a complete checkpoint.
Do not switch models halfway through a live write operation.
Update PROJECT.md before transferring the task.

## Usage control

Use existing subscriptions and supported authentication.
Do not assume those subscriptions include separate API billing.
Do not promise unlimited usage or fixed message quotas.
Keep Standard speed unless latency matters more than usage.
Avoid Max, Ultra, and parallel agents by default.
Do not send the whole prompt pack or repository into every conversation.
Keep browser tasks read-only against source code.
Return defects to the implementation owner through R1_REPAIR.md.
Compare quality through project checks, not marketing scores.

## Official sources

- https://learn.chatgpt.com/docs/models
- https://developers.openai.com/api/docs/models/gpt-6.1-sol
- https://antigravity.google/docs/models
- https://antigravity.google/docs/ide/browser
- https://antigravity.google/blog/gemini-3-8-flash-in-google-antigravity
- https://antigravity.google/blog/gemini-3-1-pro-in-google-antigravity
