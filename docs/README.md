# Documentation Index

Welcome to the **Inventory Management System** documentation.

## 📂 Documentation Structure

### [00-Core](00-Core/)
**The Source of Truth.** Start here.
- **[Database Schema](00-Core/Database_Schema.md)**: The single source of truth for all data structures.
- **[System Architecture](00-Core/System_Architecture.md)**: High-level infrastructure and stack.
- **[Configuration Decisions](00-Core/Configuration_Decisions.md)**: Global project settings and decisions.

### [01-Business Logic](01-Business_Logic/)
**Functional Requirements & Rules.**
- **[Business Rules Catalogue](01-Business_Logic/BR_Catalogue.md)**: Index of all business rules.
- **[Process Flows](01-Business_Logic/Process_Flows.md)**: Visual diagrams of system workflows.
- *Also contains detailed rules for Invoices, Collections, Shipments, FIFO, etc.*

### [02-Technical Specs](02-Technical_Specs/)
**Implementation Details.**
- **[Backend Implementation](02-Technical_Specs/Backend_Implementation.md)**: Services, Observers, and Classes plan.
- **[API Reference](02-Technical_Specs/API_Reference.md)**: Error codes and response formats.
- **[Schema Compliance Matrix](02-Technical_Specs/Schema_Compliance_Matrix.md)**: Mapping schema to code enforcement.

### [03-Security](03-Security/)
**Safety & Compliance.**
- **[Authorization Audit](03-Security/Authorization_Audit.md)**: Permission coverage and gaps.
- **[Security & Recovery](03-Security/Security_Disaster_Recovery.md)**: Backup protocols and security measures.

### [04-Operations](04-Operations/)
**DevOps & Performance.**
- **[DevOps & CI/CD](04-Operations/DevOps_CICD.md)**: Deployment pipelines.
- **[Performance Tuning](04-Operations/Performance_Tuning.md)**: Caching and optimization.

### [99-Reviews & Audit](99-Reviews_Audit/)
**Historical Records.**
-Snapshots of compliance findings, expert reviews, and architecture logs.

---

## 🚀 Recommended Reading Path

1.  **New Developers**: `00-Core/System_Architecture` → `00-Core/Database_Schema` → `02-Technical_Specs/Backend_Implementation`
2.  **Product Managers**: `01-Business_Logic/process_flows` → `01-Business_Logic/BR_Catalogue`
3.  **Auditors**: `00-Core/Database_Schema` → `03-Security/Authorization_Audit`

---

*Last Updated: 2025-12-13*
