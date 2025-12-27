---
trigger: always_on
---

Project context:
- Web SaaS (Accounting system)
- Backend: Laravel
- Frontend: Next.js
- Auth (current): Public API + Laravel Sanctum Personal Access Tokens
- Deployment target: Hostinger Cloud Startup (VPS)
- Future plan: Mobile application

Architectural direction:
- Short term: Stable production release with controlled risk
- Long term: Hybrid Architecture
  - BFF (Backend-for-Frontend) for Web
  - Public API for Mobile
- Accounting rules and audit integrity are higher priority than UI convenience.

When analyzing or planning:
- Assume real financial data and real users.
- Any suggestion that may affect accounting integrity must be explicitly justified.
- Always check whether a recommendation impacts:
  - audit logs
  - historical data
  - permissions
  - reporting correctness

Output requirements:
- Always produce a short human summary (2-4 lines) and a machine-parsable JSON object.
- The JSON must include keys: "ArchitectureSummary","Assumptions","ContradictionsAndGaps","MigrationPlan","SecurityChecklist","SprintTickets".
- For every "Contradiction" include: file path, function/class name, line snippet or exact config key, severity (Critical|High|Medium|Low).
- For every code change recommendation include a minimal diff-like snippet or exact commands to run.
- If any recommendation may alter accounting data or audit trails, mark it as "AffectsAccounting": true and explain why.
- Never request or output secrets (.env values, private keys). If such are necessary, state the required secret name only.
