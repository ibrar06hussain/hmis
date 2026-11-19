# Deployment, Offline LAN Operations & Backup / Disaster Recovery Plan
## Cardiac Hospital Gilgit — Enterprise HMIS

---

## 1. Deployment Profiles Comparison

| Parameter | Hostinger Business Demo (Temporary) | On-Premises Production (Cardiac Hospital Gilgit) |
| :--- | :--- | :--- |
| **Purpose** | Stakeholder walkthroughs & UI/workflow review ONLY | Live 24/7 clinical & hospital operations (2026–2046) |
| **Patient Data Policy** | **STRICTLY SYNTHETIC DATA ONLY** (`HMIS_DEMO_MODE=true`) | Real hospital clinical records (`HMIS_DEMO_MODE=false`) |
| **Database Engine** | MySQL 8.0+ / MariaDB 10.6+ | **PostgreSQL 16–18** (authoritative transactional DB) |
| **PHP Runtime** | PHP `8.4` (selected via Hostinger hPanel) | PHP `8.4` (PHP-FPM + OPcache tuned) |
| **Queue / Cache** | `database` driver + cron `schedule:run` every minute | Redis (`phpredis`) + Supervisor workers |
| **Internet Dependency** | Public HTTPS (demo access) | **Zero internet required** — 100% Hospital LAN |
| **PACS / LIS Devices** | Simulated (`NullPacsGateway`, synthetic HL7 contract) | Dedicated Orthanc PACS + LAN LIS Gateway Agent |

---

## 2. On-Premises Production Deployment Guide (Hospital LAN)

### 2.1 Recommended Server Baseline
- **OS:** Ubuntu 24.04 LTS Server (hardened, UFW firewall restricted to hospital VLANs).
- **Web Stack:** Nginx + PHP 8.4-FPM (`opcache.validate_timestamps=0` in production).
- **Database:** PostgreSQL 17/18 on dedicated RAID-10 NVMe storage with least-privilege `hmis_app` DB role (no superuser access from Laravel).
- **Network & TLS:**
  - Local DNS record: `hmis.chg.local` resolving to hospital application server IP.
  - Internal hospital CA certificate installed on clinical workstations so browsers trust `https://hmis.chg.local` completely offline.
  - Separate VLANs for: (1) Clinical Workstations, (2) Laboratory Analyzers / LIS Gateway, (3) DICOM Modalities & Orthanc PACS, (4) Server Management.

### 2.2 Production Installation Steps
```bash
# 1. Clone and install production dependencies (no dev packages)
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Build offline Vite assets (or deploy pre-built public/build/)
npm ci && npm run build

# 3. Configure production environment (.env NEVER committed to git)
cp .env.example .env
php artisan key:generate
# Set APP_ENV=production, APP_DEBUG=false, HMIS_DEMO_MODE=false,
# DB_CONNECTION=pgsql, CACHE_STORE=redis, QUEUE_CONNECTION=redis, SESSION_DRIVER=redis

# 4. Run database migrations and core reference seeders ONLY (never DemoDataSeeder!)
php artisan migrate --force
php artisan db:seed --class=RoleAndPermissionSeeder --force
php artisan db:seed --class=HospitalOrganizationSeeder --force
php artisan db:seed --class=TerminologySeeder --force
php artisan db:seed --class=LabAndImagingCatalogSeeder --force
php artisan db:seed --class=PharmacyAndBillingCatalogSeeder --force

# 5. Cache framework artifacts for sub-50ms LAN response times
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 3. Hostinger Business Shared Hosting Demo Deployment

> [!CAUTION]
> Shared hosting is strictly for synthetic demonstration (`HMIS_DEMO_MODE=true`). Never enter real patient names, CNICs, or clinical records on shared hosting.

1. Select **PHP 8.4** in Hostinger hPanel.
2. Build local assets on your workstation (`npm run build`) and deploy the repository (including `public/build/` and `vendor/` if SSH Composer is restricted).
3. Point domain document root to `/public` (never expose `.env` or `storage/`).
4. Configure `.env`:
   ```env
   APP_ENV=staging
   APP_DEBUG=false
   HMIS_DEMO_MODE=true
   DB_CONNECTION=mysql
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   SESSION_DRIVER=database
   ```
5. Run `php artisan migrate --seed --force` (seeds synthetic Gilgit demo accounts and sample patients).
6. Add a single Hostinger cron job every minute:
   `* * * * * /usr/bin/php /home/<user>/hmis/artisan schedule:run >> /dev/null 2>&1`

---

## 4. Backup & Disaster Recovery Plan (20-Year Horizon)

### 4.1 Recovery Objectives
- **RPO (Recovery Point Objective):** `<= 15 minutes` (via PostgreSQL continuous WAL archiving).
- **RTO (Recovery Time Objective):** `<= 60 minutes` on local warm-standby hardware.

### 4.2 3-2-1 Backup Strategy (Offline-Capable)
1. **Tier 1 (On-Server Nightly Snapshot):** Automated encrypted `pg_dump --format=custom --compress=9` every night at `02:00 PKT` + continuous WAL segment archiving.
2. **Tier 2 (Separate Hospital Building / NAS):** Automated encrypted `rsync` over hospital fiber/LAN every 15 minutes to a physically separate locked server room / NAS.
3. **Tier 3 (Offline Air-Gapped Media):** Weekly encrypted backup rotated onto offline LTO/hard drives stored in a fireproof hospital vault (ransomware protection).

### 4.3 Backup Restoration Verification Drill (Mandatory Quarterly)
A backup is **not valid** until restored and verified:
```bash
# Restore to isolated verification PostgreSQL database
pg_restore --clean --if-exists --dbname=chg_hmis_restore_drill backup_YYYYMMDD.dump

# Verify core clinical integrity counts & run smoke checks
psql -d chg_hmis_restore_drill -c "SELECT count(*) FROM patients; SELECT count(*) FROM audit_logs; SELECT count(*) FROM financial_ledger_entries;"
```

### 4.4 Power Resilience (Gilgit-Baltistan Operations)
- Online double-conversion UPS wired via NUT (`Network UPS Tools`) to gracefully checkpoint PostgreSQL and shut down servers if generator failover fails.
- PostgreSQL `synchronous_commit = on` and `full_page_writes = on` enabled to guarantee zero transaction corruption during unexpected power loss.
