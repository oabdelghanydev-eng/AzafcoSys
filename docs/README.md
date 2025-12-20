# 📚 Documentation Index

> **Inventory Management System** - نظام إدارة المخزون

**Last Updated:** 2025-12-19

---

## 📂 Documentation Structure

### [00-Core/](00-Core/) — **مصدر الحقيقة**
| File | Description |
|------|-------------|
| [Database_Schema.md](00-Core/Database_Schema.md) | هيكل قاعدة البيانات الكامل |
| [Architecture_plan.md](00-Core/Architecture_plan.md) | معمارية النظام |
| [Configuration_Decisions.md](00-Core/Configuration_Decisions.md) | قرارات الإعدادات |
| [ERD_Diagram.md](00-Core/ERD_Diagram.md) | مخطط العلاقات |

---

### [01-Business_Logic/](01-Business_Logic/) — **قواعد العمل**
| File | Description |
|------|-------------|
| [BR_Catalogue.md](01-Business_Logic/BR_Catalogue.md) | فهرس جميع قواعد العمل |
| [Process_Flows.md](01-Business_Logic/Process_Flows.md) | مخططات سير العمل |
| [BL_Invoices.md](01-Business_Logic/BL_Invoices.md) | الفواتير |
| [BL_Collections.md](01-Business_Logic/BL_Collections.md) | التحصيلات |
| [BL_Shipments.md](01-Business_Logic/BL_Shipments.md) | الشحنات |
| [BL_Inventory_FIFO.md](01-Business_Logic/BL_Inventory_FIFO.md) | نظام FIFO |
| [BL_DailyReports.md](01-Business_Logic/BL_DailyReports.md) | التقارير اليومية |
| [BL_Reports.md](01-Business_Logic/BL_Reports.md) | التقارير العامة |
| [BL_Users.md](01-Business_Logic/BL_Users.md) | المستخدمين |
| [BL_Corrections.md](01-Business_Logic/BL_Corrections.md) | التصحيحات |
| [BL_Refunds.md](01-Business_Logic/BL_Refunds.md) | المرتجعات |

---

### [02-Technical_Specs/](02-Technical_Specs/) — **المواصفات التقنية**
| File | Description |
|------|-------------|
| [Backend_Implementation.md](02-Technical_Specs/Backend_Implementation.md) | تفاصيل التنفيذ |
| [API_Reference.md](02-Technical_Specs/API_Reference.md) | مرجع الـ API |
| [Schema_Compliance_Matrix.md](02-Technical_Specs/Schema_Compliance_Matrix.md) | مصفوفة التوافق |

---

### [03-Security/](03-Security/) — **الأمان**
| File | Description |
|------|-------------|
| [Authorization_Audit.md](03-Security/Authorization_Audit.md) | تدقيق الصلاحيات |
| [Security_Disaster_Recovery.md](03-Security/Security_Disaster_Recovery.md) | الأمان و النسخ الاحتياطي |

---

### [04-Operations/](04-Operations/) — **العمليات**
| File | Description |
|------|-------------|
| [DevOps_CICD.md](04-Operations/DevOps_CICD.md) | CI/CD والنشر |
| [Performance_Tuning.md](04-Operations/Performance_Tuning.md) | تحسين الأداء |
| [env.production.template](04-Operations/env.production.template) | قالب متغيرات الإنتاج |

---

### [05-Development/](05-Development/) — **التطوير**
| File | Description |
|------|-------------|
| [Epic_4_8_Roadmap.md](05-Development/Epic_4_8_Roadmap.md) | خارطة طريق المشروع |
| [Testing_Guidelines.md](05-Development/Testing_Guidelines.md) | إرشادات الاختبار |

---

### [99-Reviews_Audit/](99-Reviews_Audit/) — **السجلات**
| File | Description |
|------|-------------|
| [2025-12-19_Invoice_API_Changes.md](99-Reviews_Audit/2025-12-19_Invoice_API_Changes.md) | آخر تغييرات API |
| [Backend_Compliance_Log.md](99-Reviews_Audit/Backend_Compliance_Log.md) | سجل توافق Backend |
| [Frontend_Compliance_Log.md](99-Reviews_Audit/Frontend_Compliance_Log.md) | سجل توافق Frontend |
| [_archive/](99-Reviews_Audit/_archive/) | ملفات مؤرشفة |

---

## 🚀 مسار القراءة الموصى

| الدور | المسار |
|-------|--------|
| **مطور جديد** | `00-Core/Architecture` → `00-Core/Database_Schema` → `02-Technical/Backend` |
| **مدير منتج** | `01-Business_Logic/Process_Flows` → `01-Business_Logic/BR_Catalogue` |
| **مدقق** | `00-Core/Database_Schema` → `03-Security/Authorization_Audit` |

---

## 📊 إحصائيات

- **إجمالي الملفات:** 25 ملف
- **المجلدات:** 6 مجلدات رئيسية
- **آخر تحديث:** 2025-12-19
