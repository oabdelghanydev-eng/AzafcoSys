# Architectural Decision Records

This directory contains binding architectural decisions for the financial system.

---

## Index

| ADR | Decision | Status | Severity |
|-----|----------|--------|----------|
| [ADR-001](ADR-001-Return-Cancellation-Ledger-Integrity.md) | Return cancellation requires service-layer orchestration | Implemented | SEV-1 |
| [ADR-002](ADR-002-Invoice-Ledger-Integrity.md) | Invoice creation requires explicit balance update | Implemented | SEV-1 |
| [ADR-003](ADR-003-Inventory-Allocation-Constraint.md) | Inventory allocations protected by database constraint | Pending Migration | HIGH |
| [ADR-004](ADR-004-Daily-Report-Immutability.md) | Daily report immutability via trait enforcement | Implemented | HIGH |

---

## Decision Criteria

All ADRs in this system follow these principles:

1. **Invariants over conventions** — Enforce rules architecturally, not by documentation
2. **Explicit over implicit** — No hidden observer magic; each code path responsible
3. **Database-level protection where possible** — CHECK constraints, NOT NULL, triggers
4. **Bypass detection** — Guards that throw exceptions when circumvented
5. **Testability** — Every decision has corresponding regression tests

---

## Date: 2025-12-27

These decisions were made following an adversarial architectural review that discovered two SEV-1 ledger corruption bugs.
