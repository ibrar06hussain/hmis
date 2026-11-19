# Software Requirements Specification (SRS)
## Cardiac Hospital Gilgit — Enterprise Hospital Management Information System (HMIS)

**Document Version:** 0.1.0 (Phase 0 Baseline)  
**Target Facility:** Cardiac Hospital Gilgit, Gilgit-Baltistan, Pakistan (100+ beds, tertiary cardiac care)  
**Operational Horizon:** 2026–2046 (20+ years)  
**Primary Timezone:** Storage in `UTC`; UI/clinical display in `Asia/Karachi` (PKT, UTC+05:00)

---

## 1. Purpose & Clinical Context

Cardiac Hospital Gilgit is a government cardiac specialty hospital (>100 beds) providing Outpatient (OPD), Emergency, Inpatient (IPD), Intensive Care Unit (ICU/CCU), Cardiac Surgery, Catheterization Laboratory, Laboratory (LIS), and Diagnostic Imaging (RIS/PACS) services.

This HMIS is a clinical-grade system—not a CRUD prototype—designed around:
1. **Patient Safety:** Permanent, non-recyclable patient identity (MRN), duplicate detection, allergy/critical-value visibility, verified result release gates, and auditability of all clinical changes.
2. **Offline-First Hospital LAN Operation:** 100% of core clinical, laboratory, pharmacy, imaging, and billing workflows operate on the local hospital network with zero dependency on external CDNs, public fonts, or internet APIs.
3. **20-Year Maintainability & Standards Interoperability:** Clean modular monolith boundaries, PostgreSQL canonical schema, HL7 FHIR R4 interoperability surface, ICD-10/ICD-11 versioned terminology, LOINC/UCUM support, and PACS (`StudyInstanceUID`) readiness from day one.

---

## 2. Existing Environment & Project State (Inspected 2026-11-18)

| Item | Observed State | Notes |
| :--- | :--- | :--- |
| Git Repository | `c:\xampp\htdocs\HMIS` on branch `main`, remote `origin` = `https://github.com/ibrar06hussain/hmis.git` | Clean working tree; contains only `README.md` (99 bytes) and `.git/`. No existing Laravel code, migrations, or packages. |
| PHP CLI | `PHP 8.2.12` (ZTS Visual C++ 2019 x64, `C:\xampp\php\php.exe`) | Extensions enabled: `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqli`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sodium`, `zip`, etc. `php_pdo_pgsql.dll` and `php_pgsql.dll` exist in `C:\xampp\php\ext\` but are not enabled in `C:\xampp\php\php.ini`. |
| Composer / Node | Composer `2.9.5`, Node.js `v24.12.0`, npm `11.6.2` | Ready for PHP and Vite local asset bundling. |
| Local Databases | PostgreSQL 18 installed (`C:\Program Files\PostgreSQL\18`); XAMPP MySQL installed (`C:\xampp\mysql\bin\mysqld.exe`) | Neither database service was running in the sandbox check; `pdo_sqlite` is available for fast unit/feature test runs alongside PostgreSQL/MySQL integration runs. |

---

## 3. High-Risk Assumptions & Missing Requirements

### 3.1 Missing Requirements (To Be Confirmed With Hospital Stakeholders)
1. **Facility Code & Existing MRN Format:** Whether Cardiac Hospital Gilgit already has an established paper/legacy MRN prefix or numbering scheme (assumed configurable prefix `CHG` -> `CHG-YYYY-NNNNNN` unless specified).
2. **CNIC / Identity Policy for Foreigners, Neonates, and Unidentified Emergency Patients:** Exact required fields for temporary emergency registrations (`UNKNOWN` / `John Doe` protocol) and guardian linkage rules for pediatric cardiac patients.
3. **Government / GB Health Department Reporting & Retention Mandates:** Exact statutory retention duration for adult vs. pediatric cardiac records, medico-legal emergency logs, and financial/fee exemptions (e.g., Sehat Card / Zakat / government entitlement categories).
4. **Specific Laboratory Analyzers & Cardiac Devices:** Exact manufacturer/model/protocol (HL7 v2 vs. ASTM vs. RS-232/file drop) for hematology, biochemistry, cardiac markers (Troponin), coagulation, blood gas, ECG, and Echo machines prior to Phase 5/6 adapter coding.
5. **Hostinger Business Shared Hosting Constraints:** Exact PHP version selectable in Hostinger hPanel (8.2 vs. 8.3/8.4), SSH/Composer availability, and cron limitations for the synthetic demo deployment.

### 3.2 High-Risk Technical & Clinical Assumptions
1. **PHP 8.2.12 vs. Laravel 13 Compatibility:** Laravel 13 (active support until Q3 2027, security until March 2028) requires **PHP 8.3+**. The local XAMPP binary is **PHP 8.2.12** (PHP 8.2 security EOL is Dec 31, 2026), which only supports up to **Laravel 12** (security fixes until Feb 24, 2027). Installing PHP 8.3/8.4 for development—or intentionally starting on Laravel 12 with a clean upgrade path—must be decided upfront.
2. **Shared Hosting vs. Production Isolation:** Hostinger shared hosting lacks reliable long-running queue workers, Redis, PostgreSQL, and DICOM/HL7 TCP listeners. Demo mode must strictly run `QUEUE_CONNECTION=database` / `sync` fallback with synthetic data only and must never be used for real patients.
3. **Clinical Safety Boundaries:** No lab result may auto-publish without human verification; no AI component may autonomously diagnose, prescribe, verify results, or discharge; all clinical records are append-only/versioned (never hard-deleted).
4. **Offline LAN Operation:** No external CSS/JS/font CDNs (`fonts.googleapis.com`, `cdn.jsdelivr.net`) or external API calls are permitted on critical clinical paths. All frontend assets (Bootstrap 5, Bootstrap Icons, Alpine/Livewire, TomSelect/choices, chart libraries) are compiled locally via Vite.

---

## 4. Non-Functional Requirements

| Category | Requirement |
| :--- | :--- |
| **Availability & Offline Resilience** | Core hospital workflows operate 24/7 on hospital LAN without internet access. |
| **Security** | RBAC + department/encounter scoping + Break-Glass emergency access with mandatory reason & audit log; Argon2id/Bcrypt password hashing; CSRF, XSS escaping, prepared statements, signed URLs for attachments. |
| **Auditability** | Immutable `audit_logs` table recording actor, role, action, entity type/ID, before/after state diffs, IP, user-agent, and UTC timestamp for clinical, identity, security, and financial mutations. |
| **Localization & Time** | English UI with `lang/` translation keys prepared for future Urdu (`ur`) RTL support. UTC persisted in DB; rendered in `Asia/Karachi`. |
| **Data Portability** | Migrations written to run cleanly on PostgreSQL (canonical production & test) and MySQL 8.0+ / MariaDB 10.6+ (Hostinger demo) with database-specific features isolated behind adapters. |
| **Testing** | Automated Pest/PHPUnit feature, unit, policy, migration, and contract tests covering positive, negative, and concurrency/failure scenarios. |
