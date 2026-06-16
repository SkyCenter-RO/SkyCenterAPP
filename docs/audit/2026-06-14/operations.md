# Operations and Deployment Audit

This document records the operational, configuration, deployment, build, queue, logging, and disaster recovery assessment of SkyCenter on June 16, 2026, on branch `audit/full-application` in the worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit`.

---

## 1. Build and Deployment Setup

### Docker Configuration
- **Dockerfile**: Located at [docker/app/Dockerfile](file:///D:/Automation/SkyPark/App/.worktrees/full-application-audit/docker/app/Dockerfile).
- **Base Image**: Uses `php:8.3-cli`.
- **Installed Extensions**: `intl`, `pdo`, `pdo_pgsql`, `zip`.
- **Web Server Command**: Runs PHP's built-in development server via `php artisan serve --host=0.0.0.0 --port=8000`.
- **Compose Stack**: Defined in [docker-compose.yml](file:///D:/Automation/SkyPark/App/.worktrees/full-application-audit/docker-compose.yml), running the app and a PostgreSQL 16 image (`postgres:16-alpine`).

### Caching Verification
The Laravel optimization cache commands were tested inside the running application container:
- **`config:cache`**: **Passed** (cached successfully).
- **`route:cache`**: **Passed** (cached successfully).
- **`view:cache`**: **Passed** (cached successfully).

---

## 2. Queues and Scheduling

### Queue Configuration
- **Default Driver**: `database` (stores jobs in the `jobs` table).
- **Table**: `jobs` (failed jobs recorded in `failed_jobs` via `database-uuids` driver).
- **Status**: The default driver is functional, but `database` queueing is less performant under high volumes than Redis or Beanstalkd.
- **Operational Gap (Critical)**: The `docker-compose.yml` does not define a queue worker container (e.g. `php artisan queue:work`). Jobs will remain in the database queue indefinitely unless a worker is started manually or on the host.

### Scheduling Configuration
- **Default Status**: `php artisan schedule:list` reports `No scheduled tasks have been defined.`
- **Schedules**: `bootstrap/app.php` and `routes/console.php` lack schedule definitions.
- **Operational Gap**: There is no containerized cron or scheduler execution loop (e.g., calling `php artisan schedule:run` every minute). If cron tasks are added in the future, they will not trigger automatically.

---

## 3. Logging and Diagnostics

- **Default Driver**: `stack` wrapping `single` (`storage/logs/laravel.log`).
- **Level**: `debug`
- **Operational Gap**: A single log file grows indefinitely and will eventually fill the disk. No log rotation (`daily` channel) is enabled by default in `.env.example`. There is also no configuration for log exporters (e.g., syslog, Fluentd) or external aggregators.

---

## 4. Disaster Recovery (Backup & Restore)

A manual backup and restore workflow was verified using the PostgreSQL container utilities:

### Backup Execution
Successfully backed up the database using `pg_dump` in custom binary archive format:
```bash
docker exec -e PGPASSWORD=secret full-application-audit-pgsql-1 pg_dump -U skycenter -d skycenter_app -F c -f /tmp/backup.dump
```

### Restore Execution
Successfully dropped, recreated, and restored the backup into `skycenter_app_test`:
```bash
# Drop the database
docker exec -e PGPASSWORD=secret full-application-audit-pgsql-1 psql -U skycenter -d postgres -c "DROP DATABASE IF EXISTS skycenter_app_test;"

# Recreate the database
docker exec -e PGPASSWORD=secret full-application-audit-pgsql-1 psql -U skycenter -d postgres -c "CREATE DATABASE skycenter_app_test OWNER skycenter;"

# Restore the dump
docker exec -e PGPASSWORD=secret full-application-audit-pgsql-1 pg_restore -U skycenter -d skycenter_app_test /tmp/backup.dump
```
**Result**: The backup restored cleanly with exit status `0`.

---

## 5. Identified Operational Gaps

| Finding ID | Severity | Title | Impact | Recommendation |
| :--- | :---: | :--- | :--- | :--- |
| **SC-AUD-023** | **Critical** | Missing automated queue worker daemon | Queued jobs (such as email and SMS notifications) are never executed unless an operator manually runs `queue:work`. | Add a queue worker service container to `docker-compose.yml` that runs `php artisan queue:work --verbose`. |
| **SC-AUD-024** | **High** | CLI server `php artisan serve` used for application delivery | The single-threaded PHP built-in CLI server is vulnerable to request bottlenecks, memory leaks, and lacks production hardening. | Transition to a production-grade application server like FrankenPHP, Swoole, RoadRunner, or Nginx + PHP-FPM. |
| **SC-AUD-025** | **Medium** | Missing scheduled task runner | Future scheduler additions will not trigger due to the lack of a cron daemon running `php artisan schedule:run`. | Add a cron worker container or daemon task to run the Laravel schedule executor every minute. |
| **SC-AUD-026** | **Low** | Log retention is configured to grow infinitely | Single log storage defaults will cause server storage exhaustion over time. | Configure `LOG_CHANNEL=daily` or use external log rotation. |
| **SC-AUD-027** | **Medium** | No automated DB backup pipeline | Disasters or database corruption require manual operator backups. | Set up a periodic cron job or n8n task that runs `pg_dump` and uploads the archives to secure offsite storage. |
