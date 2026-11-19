# Interoperability, Clinical Terminology & Equipment Integration Contracts
## Cardiac Hospital Gilgit — HMIS

---

## 1. FHIR R4-Ready Patient & Encounter Data Structure

FHIR R4 is implemented as a dedicated interoperability layer (`/api/fhir/r4`) backed by normalized relational tables—never by storing raw FHIR JSON blobs as the primary database.

### 1.1 `Patient` Resource Mapping (`patients`, `patient_identifiers`, `patient_contacts`)
| FHIR R4 Element | HMIS Source Column / Table | Notes |
| :--- | :--- | :--- |
| `Patient.id` | `patients.uuid` (UUIDv7) | Stable immutable resource ID across merges/updates |
| `Patient.identifier[mrn]` | `patients.mrn` | `system`: `urn:oid:chg:mrn` (hospital OID/URI), `use`: `usual` |
| `Patient.identifier[cnic]` | `patient_identifiers` (`system = 'cnic'`) | `system`: `https://nadra.gov.pk/cnic` (when verified/present) |
| `Patient.identifier[merged]` | `patient_identifiers` (`system = 'merged_mrn'`) | `use`: `old`, plus `Patient.link` (`type = 'replaced-by'`) when `status = 'merged'` |
| `Patient.active` | `patients.status === 'active'` | `false` for `merged` / `inactive` |
| `Patient.name` | `patients.full_name` (parsed or structured family/given) | `use`: `official` (or `anonymous` when `is_unknown_emergency = true`) |
| `Patient.gender` | `patients.sex` | Mapped cleanly: `male \| female \| other \| unknown` |
| `Patient.birthDate` | `patients.date_of_birth` | `YYYY-MM-DD` with extension if `dob_estimated = true` |
| `Patient.telecom` | `patients.phone_primary` | `system`: `phone`, `use`: `mobile` |
| `Patient.address` | `patients` / `patient_contacts` address fields | District/Tehsil/City/Address lines |
| `Patient.contact` | `patient_contacts` | Emergency/guardian relationships mapped to FHIR v2-0131 / RoleCode |

### 1.2 `Encounter` Resource Mapping (`encounters`, `departments`, `staff_profiles`)
| FHIR R4 Element | HMIS Source Column / Table | Notes |
| :--- | :--- | :--- |
| `Encounter.id` | `encounters.uuid` | Stable UUIDv7 |
| `Encounter.identifier` | `encounters.encounter_number` | e.g., `ENC-2026-000001` |
| `Encounter.status` | `encounters.status` | `planned \| arrived \| in-progress \| finished \| cancelled` |
| `Encounter.class` | `encounters.class` | `AMB` (OPD), `EMER` (Emergency), `IMP` (IPD), `CARD_PROC` (Cath/Procedure) |
| `Encounter.subject` | `Patient/{patients.uuid}` | Reference to canonical patient |
| `Encounter.participant` | `attending_staff_id` (`Practitioner/{uuid}`) | Attending clinician |
| `Encounter.serviceProvider` | `department_id` (`Organization/{uuid}`) | Hospital department |
| `Encounter.period` | `started_at`, `ended_at` | UTC ISO-8601 (`Z`) or Asia/Karachi offset |

### 1.3 Interoperability Controls
- **Endpoint Surface:** Separate `/api/fhir/r4/*` routes protected by Sanctum/OAuth2 scopes (`patient/*.read`, `encounter/*.read`, etc.), rate-limited, paginated (`Bundle` searchset), and emitting `OperationOutcome` errors.
- **No Premature Conformance Claims:** Conformance statements (`CapabilityStatement`) list only implemented, contract-tested resources.

---

## 2. ICD-10 and ICD-11 Clinical Terminology Architecture

### 2.1 Core Principles
1. **Offline-Capable Local Terminology Cache:** All ICD-10, ICD-11, LOINC, and UCUM codes used clinically are stored in local database tables (`terminology_systems`, `terminology_releases`, `terminology_codes`, `terminology_aliases`, `terminology_mappings`) so diagnosis search works 100% offline on the hospital LAN.
2. **Historical Version Preservation:** `encounter_diagnoses` persists the exact `coding_system`, `terminology_version`, `code`, and `display_name` at the time of recording. Importing a newer ICD release **never mutates historical diagnoses**.
3. **WHO Licensing & Sync Strategy:**
   - **ICD-11:** Official WHO ICD-API / local WHO ICD-11 container (or licensed distribution package) synced on an admin workstation with internet and imported via a signed offline bundle to the hospital server. Verify WHO ICD-11 & ICD-10 licensing terms prior to loading full datasets into production or public demos.
   - **Demo Dataset:** Phase 1/2 synthetic demos seed a compact, curated cardiology-relevant subset of non-proprietary/open sample terms with clear attribution.
4. **Local Clinical Aliases:** `terminology_aliases` maps common Pakistani/cardiology shorthand (e.g., `"MI"`, `"NSTEMI"`, `"ACS"`, `"CHF"`, `"RHD"`) to official ICD codes without altering canonical WHO code text.

---

## 3. Laboratory Information System (LIS) Future-Integration Contracts

### 3.1 Clinical Release Gate (Mandatory Safety Rule)
Analyzer transmission **never** publishes results directly to the patient profile. Lifecycle:
`ordered` → `collected` → `received` → `in_analysis` → `analyzer_raw_received` → `tech_validated` → `verified` (authorized pathologist/senior tech) → **visible in Unified Patient Profile**.

### 3.2 `AnalyzerAdapterInterface` (Module: `Devices` / `Lis`)
```php
namespace App\Modules\Devices\Contracts;

interface AnalyzerAdapterInterface
{
    public function protocol(): string; // e.g., 'hl7_v2', 'astm_e1394', 'file_drop', 'vendor_api'

    public function supportsDevice(DeviceDefinition $device): bool;

    /** Parse raw payload, validate checksum/structure, detect duplicates via message hash */
    public function parseInbound(string $rawPayload, DeviceDefinition $device): ParsedAnalyzerMessage;

    /** Build protocol-specific ACK/NAK (e.g., HL7 MLLP ACK or ASTM ACK/ENQ) */
    public function buildAcknowledgement(ParsedAnalyzerMessage $message, bool $accepted, ?string $error = null): string;
}
```

### 3.3 Device Registry & Gateway Design
- `analyzer_devices` table: `device_code`, `manufacturer`, `model`, `serial_number`, `firmware_version`, `protocol`, `host_or_port`, `department_id`, `interface_version`, `connection_status`, `last_heartbeat_at`.
- `analyzer_test_mappings`: maps vendor host-code + units → `lab_test_catalog_id` + UCUM conversion factor.
- `analyzer_raw_messages`: stores immutable raw payload, SHA-256 idempotency hash (duplicate rejection), parse status, and error log.
- Windows/RS-232 instruments connect via a lightweight local **LIS Gateway Agent** posting authenticated JSON/raw frames to the HMIS internal API over LAN.

---

## 4. Radiology (RIS) & Future PACS Integration Contracts

### 4.1 Day-One PACS-Ready Data Model
- `imaging_orders` generates a unique, immutable `accession_number` (e.g., `ACC-2026-000001`) at order time—ready for DICOM Modality Worklist (MWL) queries in Phase 6.
- `imaging_studies` holds `study_instance_uid` (DICOM `0020,000D`), `accession_number`, `modality`, `series_count`, `instance_count`, `pacs_server_key` (`orthanc_main`), `study_started_at`, and `viewer_launch_token` abstraction.
- **No pixel data** is ever stored in the HMIS relational database.

### 4.2 `PacsGatewayInterface` (Module: `Pacs`)
```php
namespace App\Modules\Pacs\Contracts;

interface PacsGatewayInterface
{
    public function isEnabled(): bool;

    /** Push scheduled order to DICOM Modality Worklist (Phase 6 Orthanc/DCMTK) */
    public function publishWorklistItem(ImagingOrderData $order): void;

    /** Resolve study metadata by Accession Number or StudyInstanceUID via DICOMweb QIDO-RS */
    public function findStudyByAccession(string $accessionNumber): ?PacsStudySummary;

    /** Return short-lived, role-authorized OHIF Viewer URL for a StudyInstanceUID */
    public function buildViewerUrl(string $studyInstanceUid, int $userId): ?string;
}
```
- **Phase 1–3 Behavior:** `NullPacsGateway` is bound when PACS is not yet deployed; the Patient Profile shows imaging order details + radiology report cleanly without broken links.
- **Phase 6 Behavior:** `OrthancPacsGateway` binds to the same interface; the Patient Profile automatically activates `"View Images (OHIF)"` without modifying Patient Profile UI architecture.
