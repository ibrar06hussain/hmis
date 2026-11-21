# Change Log
## Cardiac Hospital Gilgit — HMIS

All notable milestones are documented chronologically.

### [0.9.0] — 2026-11-22 (High-Velocity 60-Second Cardiac OPD Productivity Suite — Phases 1, 2 & 3)
- **Phase 1 — Rapid 60-Second Consult Mode & Bilingual Visual Rx:**
  - Added single-screen atomic consultation workspace (`/encounters/{encounter}/rapid`) consolidating vitals, structured cardiology note (draft or immediately signed), coded ICD-10/ICD-11 diagnoses, multi-drug consolidated `Prescription` (`PharmacyService::prescribeBatch` with full active drug allergy rollback/override guard), lab panels, imaging orders, and encounter completion into a single DB transaction.
  - Added `clinical_order_sets` table & `ClinicalOrderSetSeeder` with 5 hospital Cardiology Protocol Bundles (`PROTO-ACS-DAPT`, `PROTO-HTN`, `PROTO-HFREF`, `PROTO-AFIB`, `PROTO-ANGINA-WORKUP`), 1-click **Repeat Last Visit (Copy-Forward)**, tappable clinical/exam/NYHA/ECG chips, offline dot-phrase macros (`.cvs`, `.dapt`, `.htn`, `.hf`, `.fu2w`, `.erwarn`), and keyboard shortcuts (`Ctrl+Enter`, `Alt+N`).
  - Added printable **Bilingual Urdu/English OPD Prescription** (`/encounters/{encounter}/prescription-print`) with visual **`صبح (Morning)` / `دوپہر (Noon)` / `رات (Night)`** dosing schedule, Urdu meal timing (`کھانے کے بعد` / `کھانے سے پہلے`), offline QR SVG, and cardiac red-flag warnings.
- **Phase 2 — Team OPD Workflow & Personal Doctor Favorites:**
  - Added **Pre-Consult Nursing / Junior MO Vitals & Risk Triage Station** (`/opd/pre-consult`) to capture BP/HR/SpO2, cardiac risk factors, chief complaint, and allergies outside the consultant's room.
  - Added **Consultant 1-Click Co-Sign Tray** (`/emr/cosign`, gated to `emr.note.sign`) for verifying and digitally signing MO/scribe-drafted notes in one click.
  - Added **Per-Doctor Personal Favorite Order Sets** (`/emr/favorites`) with non-destructive archiving and doctor-scoped visibility.
- **Phase 3 — Governance-Gated Offline Voice / Dictation → Structured `[DRAFT]`:**
  - Added `VoiceTranscriptionGatewayInterface`, `OfflineDictationParserService`, and `POST /encounters/{encounter}/rapid/dictate-draft` routed through `AiGovernanceService` (`HMIS_AI_ENABLED=false` kill switch by default, prompt-injection scanning, mandatory `[DRAFT]` watermarking, advisory-only protocol highlighting, zero automatic prescribing/signing, and immutable `ai_interaction_logs`).

### [0.8.0] — 2026-11-20 (Offline SVG QR 2FA, Bilingual Printable Clinical/Billing Documents & Audit/Break-Glass Console)
- Added 100% offline local SVG QR code renderer (`App\Support\OfflineQrSvg` backed by `bacon/bacon-qr-code`) embedded inline on `/security/two-factor` for zero-internet TOTP authenticator scanning, as well as on printable clinical and financial documents.
- Added printable bilingual **English & Urdu** hospital documents:
  - Cardiac Inpatient **Discharge Summary** (`GET /ipd/admissions/{admission}/discharge-summary`) with active allergies, ICD-10/ICD-11 diagnoses, prescribed medications, hospital course, follow-up plan, bilingual cardiac emergency red-flag warnings, and consultant signature block.
  - **OPD Token & Visit Slip** (`GET /opd/appointments/{appointment}/slip`) with offline QR code and bilingual clinic instructions.
  - **Official Hospital / OPD Fee Receipt** (`GET /billing/invoices/{invoice}/receipt`) with itemized tariffs, Govt/Sehat Card/Zakat waivers, immutable ledger receipt numbers, and `VOIDED` watermark protection.
- Added **Admin Audit Log Viewer & Break-Glass Oversight Console** (`/admin/audit-logs`, gated strictly by `audit.view_logs` for Medical Superintendent, Hospital Director, Administrator, and IT Administrator) with security KPIs, multi-criteria filtering & quick presets, and immediate audited early revocation (`POST /admin/break-glass/{breakGlassLog}/revoke`) recording `security.break_glass.revoked`.

### [0.7.0] — 2026-11-20 (Urdu RTL Localization Scaffolding & Offline TOTP Two-Factor Authentication)
- Added bilingual **English (`en`) & Urdu (`ur`)** localization scaffolding (`lang/en/hmis.php`, `lang/ur/hmis.php`), `SetHmisLocale` middleware, `POST /locale` switcher, and offline RTL CSS rules (`[dir="rtl"]`) with zero external web fonts or CDNs.
- Added pure-PHP offline **RFC 6238 TOTP Two-Factor Authentication** (`TotpService`, encrypted `two_factor_secret` & hashed single-use `two_factor_recovery_codes` on `users`), `/security/two-factor` enrollment & disablement workflow, throttled `/two-factor/challenge` login gate, and `EnsureTwoFactorVerified` middleware with optional mandatory enforcement (`HMIS_2FA_ENFORCED=true`) for privileged hospital roles (`it_administrator`, `administrator`, `medical_superintendent`, `hospital_director`, `consultant`).
- Added immutable audit logging for `security.2fa.enrolled`, `security.2fa.disabled`, `auth.2fa_verified`, `auth.2fa_recovery_used`, and `auth.2fa_failed`.

### [0.6.0] — 2026-11-20 (Phase 6.2 & Phase 7 Foundations: Cath Lab/Cardiac OT, ICU Telemetry & AI Governance)
- Added Cath Lab PCI & Cardiac Surgery Operation Theatre module (`/cardiology/procedures`): `PROC-YYYY-NNNNNN` scheduling, contrast allergy alert, mandatory WHO Surgical Safety Checklist enforcement prior to Consultant sign-off, stent/graft/fluoroscopy structured logging, and HL7 FHIR R4 `Procedure` endpoint (`/api/fhir/r4/Procedure/{uuid}`).
- Added ICU / CCU Bedside Device Telemetry module (`/icu`, `POST /api/v1/icu/telemetry/ingest`): stages monitor/ventilator/pump observations strictly as `unverified_raw`, flags abnormal readings and sensor disconnections, and requires explicit nurse/clinician verification or artifact rejection.
- Added Phase 7 Governance-Gated AI Assistant module (`/ai-governance`): disabled by default (`HMIS_AI_ENABLED=false`), provider-neutral `AiGatewayInterface`, allow-listed read/draft tool registry, hard block on autonomous clinical actions, prompt-injection detection across user prompts & clinical notes, mandatory `[DRAFT]` watermarking, and immutable `ai_interaction_logs`.

### [0.5.0] — 2026-11-20 (Nursing MAR, HR Duty Rosters, Executive Reporting & Phase 6 Orthanc/OHIF PACS)
- Added Nursing Station & Bedside MAR (`/nursing`): Five-Rights medication administration records (mandatory clinical reason for `held`/`refused`), shift handover notes, and cardiac fluid balance (Intake/Output mL + net calculation).
- Added HR Staff Duty Rosters (`/rosters`) with conflict protection preventing double-scheduling a clinician/nurse onto the same date & shift.
- Added Executive Operational & Clinical Governance Reporting Dashboard (`/reports`) aggregating ward-by-ward bed occupancy, OPD/IPD/Code STEMI volume, LIS average turnaround time & unacknowledged critical values, billing & government/Sehat Card waivers, and active break-glass overrides.
- Added Phase 6 `OrthancPacsGateway` implementing `PacsGatewayInterface`: dynamic config binding (`HMIS_PACS_ENABLED=true`), DICOMweb QIDO-RS study lookup by Accession Number (`POST /ris/studies/{study}/sync-pacs`), and audited ephemeral OHIF Viewer sessions (`pacs_viewer_sessions`) with zero pixel data in the HMIS database.

### [0.4.0] — 2026-11-20 (Phase 4: Pharmacy, Billing & CI/DR)
- Added Hospital Pharmacy module: cardiac formulary (`medications`), prescriptions (`RX-YYYY-NNNNNN`), active medication allergy conflict blocking with audited override, atomic stock deduction (`lockForUpdate()`), and immutable `stock_movements`.
- Added Billing & Accounts module: service tariffs, invoices (`INV-YYYY-NNNNNN`), Government/Sehat Card/Zakat/Indigent exemptions, overpayment protection, void reversals, and strictly immutable `financial_ledger_entries`.
- Added HL7 FHIR R4 `MedicationRequest` endpoint (`/api/fhir/r4/MedicationRequest/{uuid}`).
- Added GitHub Actions CI workflow (`.github/workflows/ci.yml`) running Laravel Pint, Vite build, and automated PHPUnit test matrix across **PostgreSQL 17**, **MySQL 8.0**, and **SQLite**.
- Added Deployment, Offline LAN & Backup/Disaster Recovery plan (`docs/08-deployment-and-dr.md`).

### [0.3.0] — 2026-11-20 (Phase 3: LIS, Analyzer Gateway & RIS/PACS-Ready)
- Added Laboratory Information System (`lab_test_catalog` with LOINC/UCUM/sex & critical ranges, `lab_orders`, barcode `specimens` with reject-and-reissue workflow, 2-step technical validation & authorized Pathologist verification gate, critical value acknowledgement).
- Added Devices & Analyzer Gateway (`analyzer_devices`, `analyzer_raw_messages` with SHA-256 duplicate rejection, `Hl7V2AnalyzerAdapter`, `/api/v1/lis/analyzer/ingest` staging without auto-publication).
- Added Radiology Information System (`imaging_catalog`, `ACC-YYYY-NNNNNN` accession numbers, IV contrast dye allergy alert, radiologist reporting/verification, PACS `StudyInstanceUID` linkage).
- Added HL7 FHIR R4 `DiagnosticReport` endpoint (`/api/fhir/r4/DiagnosticReport/{uuid}`, gated to verified orders only).

### [0.2.0] — 2026-11-20 (Phase 2: Emergency, Ward/Bed IPD & Offline ICD-10/11 Terminology)
- Added Cardiac Emergency & Code STEMI Triage (`/emergency`).
- Added Ward & Bed Management occupancy state machine (`available` → `occupied` → `cleaning` → `available`) and IPD Admissions/Transfers/Discharges (`ADM-YYYY-NNNNNN`) with concurrency-safe bed conflict prevention (`/ipd`).
- Added offline-capable ICD-10 & ICD-11 Clinical Terminology catalogue with clinical aliases and version-locked `encounter_diagnoses`.
- Added HL7 FHIR R4 `Condition` endpoint (`/api/fhir/r4/Condition/{uuid}`).

### [0.1.0] — 2026-11-20 (Phase 0 & Phase 1: Foundation, MPI, OPD & Structured EMR)
- Provisioned Laravel 13 (`v13.42.0`) + PHP 8.4 + locally bundled Bootstrap 5 (zero external CDNs).
- Implemented Core, Organization, IAM (12+ roles, department scoping, audited Break-Glass), Audit (`audit_logs`), Master Patient Index (`CHG-YYYY-NNNNNN` atomic MRN, no-CNIC emergency intake, duplicate detection, audited patient merge), Unified Patient Profile, OPD Appointments/Queue, Vitals with cardiac abnormal flags, and Structured Cardiology Notes with digital sign-off & immutable amendment revisions.
- Added `/api/v1/patients`, `/api/v1/appointments`, `/api/fhir/r4/Patient/{uuid}`, and `/api/fhir/r4/Encounter/{uuid}`.
