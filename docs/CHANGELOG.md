# Change Log
## Cardiac Hospital Gilgit — HMIS

All notable milestones are documented chronologically.

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
