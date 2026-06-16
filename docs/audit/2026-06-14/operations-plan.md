# Technical Documentation and Operations Plan

This document defines the technical documentation overhaul and production operations plan for SkyCenter, compiled on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Local Setup and Environment Provisioning

To address the reproducible build and runtime environment gaps, the following setup checklist must be implemented in the technical documentation:

### Developer Setup Commands
1. Clone the repository and navigate to the project directory.
2. Copy the environment file: `cp .env.example .env`.
3. Start the Docker services: `docker compose up -d`.
4. Install PHP dependencies: `docker compose exec app composer install`.
5. Generate application key: `docker compose exec app php artisan key:generate`.
6. Run database migrations and seeders: `docker compose exec app php artisan migrate --seed`.
7. Install node packages and compile frontend: `docker compose exec app npm install && docker compose exec app npm run build` (Note: requires updating the App Dockerfile to include Node.js).

---

## 2. Environment Variables and Secret Protection

The following variables must be configured and kept secret:

| Variable | Description | Source | Security |
| :--- | :--- | :--- | :--- |
| `APP_KEY` | Laravel encryption key. | Generated | Must never be committed. |
| `AUTOMATION_API_TOKEN` | Bearer token for webhook API routes. | Generated | Must be unique per env. |
| `ADMIN_BOOTSTRAP_PASSWORD` | Default boot password for AdminUserSeeder. | Operator | Must be overwritten in env. |
| `DB_PASSWORD` | PostgreSQL connection password. | DB Config | Must not use default `secret`. |

---

## 3. Queue Daemon Configuration

To resolve **SC-AUD-023** (Missing automated queue worker), we plan to define supervised queue worker daemons:

### Docker Compose Service Definition
Add a queue service worker to `docker-compose.yml` to run in parallel with the app container:
```yaml
  queue:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    working_dir: /var/www/html
    command: php artisan queue:work --tries=3 --timeout=90
    environment:
      APP_ENV: local
      DB_CONNECTION: pgsql
      DB_HOST: pgsql
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-skycenter_app}
      DB_USERNAME: ${DB_USERNAME:-skycenter}
      DB_PASSWORD: ${DB_PASSWORD:-secret}
    depends_on:
      pgsql:
        condition: service_healthy
```

---

## 4. Disaster Recovery and Automated Backups

To resolve **SC-AUD-027** (No automated DB backup pipeline), the following backup cron strategy is defined:

### Automated Backup Script (`/usr/local/bin/backup-db.sh`)
```bash
#!/bin/bash
set -e
BACKUP_DIR="/var/backups/skycenter"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/skycenter_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

# Run pg_dump and compress
PGPASSWORD="${DB_PASSWORD}" pg_dump -h "${DB_HOST}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" | gzip > "$BACKUP_FILE"

# Prune backups older than 30 days
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +30 -delete
```

---

## 5. Caching and Deployment Warmup

Production deployments must run the following sequence to optimize performance:
```bash
# Warm up config and route caches
php artisan config:cache
php artisan route:cache

# Pre-compile Blade templates
php artisan view:cache

# Restart queue workers to load updated code
php artisan queue:restart
```

---

## 6. Overhauling the Root README.md

The root `README.md` must be replaced to provide a complete guide to incoming engineers.

### Proposed README Structure
1. **Project Title and Domain Overview**: Explains SkyCenter as the operational core for Parking, Lodging, and Rentals.
2. **Architecture Map**: Identifies the Laravel-Filament structure, PostgreSQL database, and n8n/Telegram integrations.
3. **Local Quickstart**: Reproducible Docker-based environment creation.
4. **Testing Instructions**: Local SQLite test database settings and verification commands.
5. **Operational Guide**: Queue daemon checks, log rotation, and database backup scripts.
6. **Release Procedures**: Maintenance commands and cache warmup checklists.
7. **Contact and Support**: Internal maintainers and escalation procedures.

---

## 7. Identified Documentation Gaps

| Finding ID | Severity | Title | Impact | Recommendation |
| :--- | :---: | :--- | :--- | :--- |
| **SC-AUD-036** | **Low** | Root README.md remains the Laravel framework default | Incoming developers lack documentation on SkyCenter features, configurations, and integrations. | Overhaul the root `README.md` according to the structure proposed in this plan. |
