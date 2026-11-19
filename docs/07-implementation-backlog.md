# Implementation Backlog & Phase 1 Acceptance Criteria
## Cardiac Hospital Gilgit — HMIS

---

## 1. Short, Testable Milestones (Phase 0 – Phase 7)

### Phase 0 — Foundation & Architecture (Current Gate)
- **M0.1 — Architecture, ERD & Specs (This Deliverable):** SRS, Modular Monolith folder structure, 28-module map, ERD, MRN strategy, RBAC matrix, FHIR/ICD/LIS/PACS contracts, MySQL compatibility plan.
- **M0.2 — Laravel Bootstrap & Local Asset Pipeline:** Initialize Laravel application (version confirmed with stakeholder), Vite + Bootstrap 5 + Bootstrap Icons + Alpine/Livewire local bundle (zero CDN calls), Pest/PHPUnit + Larastan + Pint configuration, `Asia/Karachi` presentation helper, UTC DB config, `/health` endpoint.

### Phase 1 — Hospital Foundation (Vertical Slices)
- **M1.1 — Core, Organization & IAM + Audit Foundation:**
  - Migrations: `facilities`, `departments`, `wards`, `users`, `staff_profiles`, Spatie `roles`/`permissions`, `audit_logs`, `break_glass_logs`.
  - Seeders: Cardiac Hospital Gilgit departments (Cardiology OPD, Emergency, CCU, Cardiac Surgery, Cath Lab, Lab, Radiology, Pharmacy, Admin), 12 roles + permissions, secure demo users (`APP_DEMO_MODE=true` gated).
  - Login, role-specific dashboard shell, workstation lock/logout, audit middleware, policy tests.
- **M1.2 — Master Patient Index (MPI) & Permanent MRN Registration:**
  - Migrations: `mrn_sequences`, `patients`, `patient_identifiers`, `patient_contacts`, `patient_merges`.
  - Services: `MrnGeneratorService` (atomic `CHG-YYYY-NNNNNN` on both PostgreSQL & MySQL/SQLite), `DuplicateDetectionService`, `PatientRegistrationService` (standard + emergency no-CNIC), `PatientMergeService`.
  - Screens: Fast patient search (MRN/CNIC/Phone/Name), standard & emergency registration forms, duplicate-warning modal, printable MRN card.
  - Tests: Concurrency MRN uniqueness, CNIC normalization, duplicate detection, emergency no-CNIC registration, merge integrity & audit logs, RBAC policy denial tests.
- **M1.3 — Unified Patient Profile Shell & Safety Strip:**
  - Patient Banner (MRN, demographics, emergency unverified flag, critical allergy alert strip), tabbed clinical layout covering all 21 clinical domains (active sections + clean Phase 2/3 extension points), break-glass access prompt + audit logging.
- **M1.4 — Appointments & OPD Queue Management:**
  - Migrations: `appointments`, `encounters`.
  - Clinic slots, token numbering per department/clinician/day, receptionist check-in → OPD consultation queue.
- **M1.5 — Basic OPD Clinical Documentation & Vitals:**
  - Migrations: `vital_signs`, `patient_allergies`, `clinical_notes`.
  - Nurse/MO vitals entry (BP, HR, SpO2, Temp, RR, Weight/Height/BMI), allergy entry, structured OPD consultation note (Chief Complaint, HPI, Exam, Plan), sign-off & immutable amendment revision workflow.

### Phase 2 — Clinical Management
- **M2.1:** Emergency Department triage & stabilization + Unknown-Patient-to-Known merge flow.
- **M2.2:** Ward & Bed Management state machine + IPD Admission / Transfer / Discharge.
- **M2.3:** Terminology Module (offline ICD-10/ICD-11 catalogue) & Encounter Diagnoses (primary/secondary, version-locked).
- **M2.4:** Nursing Records (MAR, nursing notes, I/O charts) & Discharge Summaries + Cardiology structured templates.

### Phase 3 — Laboratory (LIS) & Diagnostic Imaging (RIS)
- **M3.1:** Lab test catalogue, panels, LOINC/UCUM fields, Lab Order & Barcode Label generation.
- **M3.2:** Specimen collection, receipt, rejection/recollection, manual result entry, reference ranges, critical value alerts, 2-step verification gate.
- **M3.3:** Imaging catalogue, Accession numbers, Imaging Orders, Radiology reports, `imaging_studies` (`StudyInstanceUID` placeholder) + `NullPacsGateway`.

### Phase 4 — Hospital Operations
- **M4.1:** Pharmacy formulary, prescriptions, dispensing, stock checks.
- **M4.2:** Billing & Payments (tariffs, invoices, exemptions, immutable financial ledger).
- **M4.3:** Inventory & Procurement, Operation Theatre scheduling, HR & Duty Rosters, Operational Reporting Dashboards.

### Phase 5 — Standards Interoperability & Device Adapters
- **M5.1:** `/api/fhir/r4` endpoints (`Patient`, `Practitioner`, `Organization`, `Location`, `Encounter`, `Condition`, `AllergyIntolerance`, `Observation`, `ServiceRequest`, `DiagnosticReport`, `MedicationRequest`, `ImagingStudy`) + contract tests.
- **M5.2:** Full WHO ICD-10/ICD-11 offline sync mechanism & `AnalyzerAdapterInterface` implementations (HL7 v2 / ASTM / file-drop) against real vendor specs.

### Phase 6 — PACS & Critical Care Hardware Integration
- **M6.1:** Orthanc DICOM server + OHIF Viewer (`OrthancPacsGateway`) + DICOM Modality Worklists (`accession_number`).
- **M6.2:** Cardiology (Cath Lab/Echo/ECG) & ICU bedside monitor ingestion gateway.

### Phase 7 — Future AI Capabilities (Disabled by Default)
- **M7.1:** Provider-neutral LLM adapter, controlled tool registry, prompt-injection guard, human-approved draft summarization & administrative helpers (`AI_ENABLED=false` kill switch).

---

## 2. Phase 1 Acceptance Criteria (Definition of Done)

Phase 1 is **not complete** until every criterion below passes automated and manual verification:

1. **Zero External Dependencies:** Rendering login, registration, patient profile, and OPD screens makes **0** outbound requests to CDNs, external fonts, or external APIs (verified in browser network tab / Vite manifest check).
2. **Database Portability:** All Phase 1 migrations, seeders, and automated tests pass on **PostgreSQL** (canonical) and **MySQL/SQLite** (Hostinger demo compatibility).
3. **Permanent MRN & Identity Integrity:**
   - Every registered patient receives a unique, immutable `CHG-YYYY-NNNNNN` MRN.
   - Emergency registration succeeds without CNIC and flags `identity_verification_status = 'unverified'`.
   - Duplicate CNIC or Phone+Name triggers clear duplicate-match warnings prior to insert.
   - Patient merge preserves 100% of linked encounters, allergies, vitals, and clinical notes, keeps source MRN resolvable as an alias, and writes `patient_merges` + `audit_logs`.
4. **Security & Authorization:**
   - Unauthenticated access redirects to login; API endpoints return `401`/`403`.
   - Receptionist cannot view or edit clinical notes/vitals; Nurse/MO cannot merge patients; cross-department clinical access without assignment requires logged Break-Glass with mandatory reason.
   - Every registration, merge, clinical note sign-off, amendment, and break-glass action writes an immutable `audit_logs` record.
5. **Clinical Documentation & Timezone:**
   - Dates/times are stored in UTC and displayed in `Asia/Karachi`.
   - Signed clinical notes cannot be overwritten; amendments create a new revision linked via `supersedes_note_id`.
6. **Responsive UI & Quality Gates:**
   - Desktop and tablet/mobile viewports render cleanly without horizontal overflow.
   - `php artisan test`, static analysis (`larastan`), and formatting (`pint --test`) pass with zero failures.
