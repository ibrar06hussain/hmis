# ADR-0001: Modular Monolith Architecture, Technology Stack & Dual-DB Strategy

- **Status:** Accepted (2026-11-18 — Laravel 13 + PHP 8.4 approved)
- **Date:** 2026-11-18
- **Decision Drivers:** 20-year operational lifespan (2026–2046), offline hospital LAN operation, Hostinger Business Shared Hosting demo requirement, PostgreSQL production requirement, local XAMPP PHP 8.2.12 environment.

## Context
The repository is currently empty (`README.md` only). Local inspection shows PHP `8.2.12`, Composer `2.9.5`, Node `24.12.0`, PostgreSQL `18` installed, and XAMPP MySQL installed. Laravel 13 (latest stable, March 2026) requires PHP `8.3+`, whereas Laravel 12 supports PHP `8.2–8.5` (security fixes until Feb 24, 2027).

## Decision
1. **Modular Monolith (`app/Modules/*`):** Enforces domain boundaries across 28 clinical/operational modules via PHP `Contracts` and `Events`, avoiding premature microservice complexity while maintaining clean separation for a small-to-medium hospital engineering team over 20 years.
2. **Frontend Stack:** Laravel Blade + Livewire + Alpine.js + Bootstrap 5 compiled locally via Vite (`npm run build`), bundled with zero CDN dependencies for offline LAN reliability.
3. **Database Strategy:** PostgreSQL as canonical production & CI test database; portable Eloquent migrations + repository adapters for MySQL 8+ compatibility during Hostinger demo.
4. **Framework Target:** Recommend **Laravel 13 + PHP 8.3/8.4** if local/Hostinger PHP can be set to 8.3+, or **Laravel 12 + PHP 8.2.12** for immediate local execution with zero runtime changes and a straightforward Laravel 13 upgrade path.

## Consequences
- Positive: Simple deployment on both shared hosting (demo) and on-premises hospital server (production); strict clinical safety transactions inside a single ACID database; no network latency between modules.
- Negative: Requires discipline (enforced via architecture tests/reviews) to prevent cross-module model coupling.
