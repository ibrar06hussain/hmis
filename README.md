# Cardiac Hospital Gilgit — Enterprise HMIS

Production-grade, offline-capable, modular monolith Hospital Management Information System (HMIS) built with **Laravel 13**, **PHP 8.4+**, **PostgreSQL** (canonical production, with MySQL 8+ demo compatibility), **Livewire**, and locally bundled **Bootstrap 5**.

## Architecture & Documentation

See [docs/README.md](./docs/README.md) for the complete Phase 0–1 specifications:
- [Software Requirements Specification (SRS)](./docs/01-srs.md)
- [Modular Monolith Architecture & 28-Module Dependency Map](./docs/02-architecture-and-modules.md)
- [Database Conventions & Initial ERD](./docs/03-database-erd-and-conventions.md)
- [Patient Registration Workflow, MRN Strategy & Unified Patient Profile](./docs/04-patient-registration-and-profile.md)
- [Roles, Permissions & Break-Glass Access Control](./docs/05-roles-and-permissions.md)
- [FHIR R4, ICD-10/11, LIS & PACS Integration Contracts](./docs/06-interoperability-and-terminology.md)
- [Implementation Backlog & Phase 1 Acceptance Criteria](./docs/07-implementation-backlog.md)

## Local Setup (PHP >= 8.4 & Node.js)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan test
```

> **Clinical Safety Notice:** Never seed synthetic demo data (`HMIS_DEMO_MODE=true`) into a production hospital database, and never use real patient data in automated tests or public demonstrations.
