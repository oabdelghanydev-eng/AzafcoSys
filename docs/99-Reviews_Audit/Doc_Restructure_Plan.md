# Documentation Reorganization Strategy

**Date:** 2025-12-13
**Architect:** Senior CTO
**Objective:** Normalize documentation into a scalable, professional architecture.

---

## A) Final Documentation Folder Structure

We will adopt a **Number-Prefixed Layered Architecture** to enforce reading order and separation of concerns.

```text
docs/
├── 00-Core/                 # Source of Truth & High-Level Decisions
├── 01-Business_Logic/       # Rules, Flows, and Requirements
├── 02-Technical_Specs/      # Implementation Plans, API, Database
├── 03-Security/             # Auth, Permissions, Compliance
├── 04-Operations/           # DevOps, Deployment, Performance
└── 99-Reviews_Audit/        # Snapshots of reviews, findings, and audits
```

---

## B) File Placement Map

| Existing File | Current Location | **New Location** | Reason |
|:---|:---|:---|:---|
| `Database_Schema.md` | `docs/` | `00-Core/Database_Schema.md` | **The Single Source of Truth**. |
| `Architecture_plan.md` | `docs/` | `00-Core/System_Architecture.md` | High-level system design. |
| `Configuration_Decisions.md` | `docs/` | `00-Core/Configuration_Decisions.md` | Global project configuration. |
| `Business_Logic/*` | `docs/Business_Logic/` | `01-Business_Logic/*` | detailed business rules (keep catalogue here). |
| `Process_Flows.md` | `docs/Business_Logic/` | `01-Business_Logic/Process_Flows.md` | Visual workflows of business logic. |
| `Backend_Plan.md` | `docs/` | `02-Technical_Specs/Backend_Implementation.md` | Detailed tech spec (Classes, Services). |
| `API_Error_Codes.md` | `docs/` | `02-Technical_Specs/API_Reference.md` | Developer-facing API contract. |
| `Schema_Backend_Matrix.md` | `docs/` | `02-Technical_Specs/Schema_Compliance_Matrix.md` | Living tech reference for enforcement. |
| `authorization_coverage_review.md`| `docs/` | `03-Security/Authorization_Audit.md` | Security-specific coverage focused on Auth. |
| `Security_Backup.md` | `docs/` | `03-Security/Security_Disaster_Recovery.md` | Security protocols and backups. |
| `DevOps_CICD.md` | `docs/` | `04-Operations/DevOps_CICD.md` | Deployment pipelines. |
| `Performance_Caching.md` | `docs/` | `04-Operations/Performance_Tuning.md` | Optimization guidelines. |
| `Expert_Review.md` | `docs/` | `99-Reviews_Audit/2025-Expert_Review.md` | Historical audit record. |
| `backend_compliance_findings.md`| `docs/` | `99-Reviews_Audit/Backend_Compliance_Log.md` | Tracker for deviations/gaps. |
| `Documentation_Architecture_Review.md` | `docs/` | `99-Reviews_Audit/Doc_Architecture_Log.md` | Record of this reorganization effort. |
| `README.md` | `docs/` | `docs/README.md` | **Remains at Root** as the index. |

---

## C) Responsibility Definition

### `00-Core`
*   **Purpose:** The immutable foundation of the project.
*   **Contains:** Data structures, global configurations, high-level architecture.
*   **NEVER:** Implementation details, temporary findings, or specific business edge cases.

### `01-Business_Logic`
*   **Purpose:** "What the system does" (Functional Requirements).
*   **Contains:** Business Rules (BR-XXX), workflow diagrams, calculation formulas.
*   **NEVER:** SQL queries, PHP class names, or server configurations.

### `02-Technical_Specs`
*   **Purpose:** "How the system works" (Technical Design).
*   **Contains:** Class diagrams, Service methods, API specs, Observers, Database vs Code mappings.
*   **NEVER:** User requirements without technical context.

### `03-Security`
*   **Purpose:** Protection and Compliance.
*   **Contains:** Authorization matrices, permission definitions, backup policies, audit logs.
*   **NEVER:** Publicly exposed secrets or generic coding tips.

### `04-Operations`
*   **Purpose:** Running the system.
*   **Contains:** CI/CD pipelines, server setup, caching strategies, performance limits.
*   **NEVER:** Business logic rules.

### `99-Reviews_Audit`
*   **Purpose:** Historical record and Gap Tracking.
*   **Contains:** Compliance checks, code reviews, deviation logs.
*   **NEVER:** The "current" source of truth (this folder is a SNAPSHOT).

---

## D) Reading Path Map

### 1. New Developer Onboarding
> **Path:** `README.md` → `00-Core/Architecture` → `00-Core/Schema` → `02-Technical/Backend_Plan`
> **Goal:** Understand *Structure* then *Data* then *Code*.

### 2. Feature Development
> **Path:** `01-Business_Logic/(Module)` → `00-Core/Schema` → `02-Technical/Code_Map`
> **Goal:** Understand *Requirement* then *Data Constraints* then *Implementation*.

### 3. Security / Audit Review
> **Path:** `00-Core/Schema` (Permissions) → `03-Security/Authorization_Audit` → `03-Security/Security_Protocol`
> **Goal:** Verify permission definitions against enforcement.

### 4. Production Troubleshooting
> **Path:** `04-Operations/DevOps` → `02-Technical/API_Reference` → `00-Core/Config`
> **Goal:** Check *Deployment* then *Error Codes* then *Settings*.

---

## E) Guard Rules

1.  **Strict Source of Truth:** If a Business Rule contradicts `00-Core/Database_Schema.md`, the Schema wins until updated.
2.  **No Reviews in Active folders:** Files named "Review", "Findings", "Log", or "Audit" MUST go to `99-Reviews_Audit`. They are *observations*, not *specs*.
3.  **Forbidden Root:** No new files allowed in `docs/` root except `README.md`.
4.  **Schema First:** Every new feature MUST start with a Schema update in `00-Core` before writing Tech/Business docs.
5.  **Clean Naming:** Filenames must use `Snake_Case` or `PascalCase`. No spaces.
6.  **Dependency Flow:** Docs in `02` can reference `01` and `00`. `00` should rarely reference `02` (Circular dependency avoidance).
