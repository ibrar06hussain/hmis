# Roles, Permissions & Access Control Design
## Cardiac Hospital Gilgit — HMIS

---

## 1. Access Control Model

Authorization combines **three layers**:
1. **RBAC (Roles & Fine-Grained Permissions)** via `spatie/laravel-permission` using `<module>.<action>` naming.
2. **Department & Encounter Scoping (Laravel Policies):** Clinical staff only access patients with an active/recent encounter in their assigned department or explicit care-team assignment—preventing unrestricted hospital-wide browsing of VIP/staff/all patient records.
3. **Break-Glass Emergency Access:** Authorized clinicians (`consultant`, `medical_officer`, `nurse` in ER/ICU) can temporarily override department scoping for a specific patient by submitting a mandatory reason (`emergency_cardiac_arrest`, `unconscious_patient`, etc.), generating a time-boxed grant (e.g., 4 hours) and an immediate high-priority `break_glass_logs` + `audit_logs` alert to the Medical Superintendent & IT Security.

---

## 2. Core Hospital Roles (12 Operational Roles + Break-Glass)

| Role Key | Display Name | Primary Dashboard | Clinical/Record Scope |
| :--- | :--- | :--- | :--- |
| `hospital_director` | Hospital Director | Executive KPI Dashboard | Aggregate operational, bed, financial, and audit summaries (no raw clinical editing) |
| `medical_superintendent` | Medical Superintendent | Clinical Governance Dashboard | Hospital-wide clinical quality, break-glass alerts, TAT, mortality/morbidity oversight |
| `administrator` | Hospital Administrator | Operations Dashboard | Departments, staff directory, master catalogues, non-clinical admin |
| `consultant` | Consultant Cardiologist / Surgeon | Consultant Clinical Workspace | Assigned/department patients, full EMR, diagnoses, orders, prescriptions, procedure notes, sign-off |
| `medical_officer` | Medical Officer / Resident | MO OPD/ER/Ward Workspace | Department patients, history, vitals, clinical notes, draft orders/prescriptions (co-sign where configured) |
| `nurse` | Staff / Charge Nurse | Nursing Station Dashboard | Ward/ER/ICU assigned patients, vitals, MAR, nursing notes, specimen collection |
| `lab_technician` | Laboratory Technician / Pathologist | LIS Workbench | Lab orders, specimen receipt, QC, result entry (`lab.verify` restricted to pathologist/senior tech) |
| `radiologist` | Radiologist | RIS Reporting Workbench | Imaging orders, modality schedules, radiology report drafting & verification |
| `pharmacist` | Pharmacist | Pharmacy Dispensing Queue | Prescriptions verification, dispensing, drug interaction alerts, pharmacy stock |
| `billing_officer` | Billing & Accounts Officer | Billing & Cashier Desk | Patient demographics (minimal), invoices, payments, exemptions/Sehat Card |
| `receptionist` | Reception / Registration Clerk | Front Desk & Queue | Patient registration, duplicate check, appointments, OPD queue tokens (no full EMR notes/labs) |
| `it_administrator` | IT Administrator | System Health & Security | User accounts, role assignments, device registry, audit logs, backups (no routine clinical viewing without break-glass) |

---

## 3. Phase 1 Permission Matrix (Selected Core Permissions)

| Permission | Receptionist | Nurse | MO | Consultant | Lab Tech | Radiologist | Pharmacist | Billing | Admin / MS | IT Admin |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| `patient.view_demographics` | Yes | Yes | Yes | Yes | Scoped | Scoped | Scoped | Yes | Yes | No* |
| `patient.register` | Yes | ER Only | ER Only | No | No | No | No | No | Yes | No |
| `patient.update_demographics` | Yes | No | No | No | No | No | No | No | Yes | No |
| `patient.merge` | No | No | No | No | No | No | No | No | Yes (MRO/Admin) | No |
| `patient.view_clinical_profile` | No | Scoped | Scoped | Scoped | Lab Only | RIS Only | Rx Only | No | MS Governance | No* |
| `encounter.create_opd` | Yes | No | Yes | Yes | No | No | No | No | No | No |
| `encounter.create_emergency` | Yes | Yes | Yes | Yes | No | No | No | No | No | No |
| `emr.vitals.record` | No | Yes | Yes | Yes | No | No | No | No | No | No |
| `emr.note.create` | No | Nursing | Yes | Yes | No | No | No | No | No | No |
| `emr.note.sign` | No | No | Limited | Yes | No | No | No | No | No | No |
| `emr.diagnosis.record` | No | No | Yes | Yes | No | No | No | No | No | No |
| `audit.view_logs` | No | No | No | No | No | No | No | No | MS/Director | Yes |
| `iam.manage_users` | No | No | No | No | No | No | No | No | Admin | Yes |
| `iam.break_glass` | No | ER/ICU | Yes | Yes | No | No | No | No | No | No |

*\*IT Administrator manages system configuration, accounts, and logs without unrestricted clinical chart access.*

---

## 4. Security Controls
- **2FA Policy:** Mandatory TOTP 2FA (local RFC 6238 authenticator app—no SMS/internet required) for `it_administrator`, `administrator`, `medical_superintendent`, `hospital_director`, and `consultant` in production; configurable toggle for demo environments.
- **Session Hardening:** Idle session timeout (15 min clinical stations, configurable), single-click workstation lock screen for shared nursing/OPD terminals, HTTP-only/SameSite=Lax/Secure cookies.
- **API Authentication:** Laravel Sanctum tokens with strict ability scopes for internal tools; dedicated OAuth2/SMART-on-FHIR client credentials/scopes for `/api/fhir/r4`.
