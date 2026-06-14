# SkyCenter Reproducible Baseline

Evidence was collected on 2026-06-14 in the linked worktree `D:\Automation\SkyPark\App\.worktrees\full-application-audit` on branch `audit/full-application`. Commands executed in the application image used an ephemeral test key; no key value is recorded here. Durations are wall-clock measurements and timestamps use Europe/Bucharest (`+03:00`).

## Repository

### Commit identifier

- Command: `git rev-parse HEAD`
- Timestamp: `2026-06-14T10:35:16.834+03:00`
- Environment: host PowerShell in the audit worktree
- Exit code: `0`
- Duration: `0.039s`
- Finding IDs: none
- Result: `cde21de0d0c7573115e5359f7e0527082e9d06e6`

### Initial worktree state

- Command: `git status --short`
- Timestamp: `2026-06-14T10:35:17.013+03:00`
- Environment: host PowerShell in the audit worktree
- Exit code: `0`
- Duration: `0.051s`
- Finding IDs: none
- Result: no output; the worktree was clean before evidence collection.

## Containers

### Docker server

- Command: `docker version --format '{{.Server.Version}}'`
- Timestamp: `2026-06-14T10:35:17.067+03:00`
- Environment: host Docker Engine
- Exit code: `0`
- Duration: `0.087s`
- Finding IDs: none
- Result: Docker server `29.4.3`.

### Docker Compose

- Command: `docker compose version`
- Timestamp: `2026-06-14T10:35:17.156+03:00`
- Environment: host Docker Compose plugin
- Exit code: `0`
- Duration: `0.155s`
- Finding IDs: none
- Result: Docker Compose `v5.1.4`.

### Active Compose project status

- Command: `docker compose -p app -f D:\Automation\SkyPark\App\docker-compose.yml ps`
- Timestamp: `2026-06-14T10:45:05.863+03:00`
- Environment: host Docker Compose targeting the active `app` project with the main checkout Compose file
- Exit code: `0`
- Duration: `0.175s`
- Finding IDs: `SC-AUD-002`
- Result: Compose reported `app-app-1` up with host port `8080` mapped to container port `8000`, and `app-pgsql-1` up and healthy with host port `55433` mapped to container port `5432`.

### Existing application stack

- Command: `docker ps --filter name=app-app-1 --filter name=app-pgsql-1`
- Timestamp: `2026-06-14T10:35:17.316+03:00`
- Environment: existing Docker project `app` on network `app_default`
- Exit code: `0`
- Duration: `0.121s`
- Finding IDs: `SC-AUD-002`
- Result: `app-app-1` was up with host port `8080` mapped to container port `8000`; `app-pgsql-1` was up and healthy with host port `55433` mapped to container port `5432`.

### Application container state

- Command: `docker inspect app-app-1 --format <status/health/network summary>`
- Timestamp: `2026-06-14T10:35:17.442+03:00`
- Environment: existing Docker project `app`
- Exit code: `0`
- Duration: `0.088s`
- Finding IDs: none
- Result: `status=running`, no container health check configured, network `app_default`.

### Database container state

- Command: `docker inspect app-pgsql-1 --format <status/health/network summary>`
- Timestamp: `2026-06-14T10:35:17.533+03:00`
- Environment: existing Docker project `app`
- Exit code: `0`
- Duration: `0.070s`
- Finding IDs: `SC-AUD-002`
- Result: `status=running`, `health=healthy`, network `app_default`.

### Isolated Compose port configuration

- Command: `docker compose config` (database published-port excerpt)
- Timestamp: `2026-06-14T10:35:17.605+03:00`
- Environment: audit worktree Compose configuration; project was not started
- Exit code: `0`
- Duration: `0.209s`
- Finding IDs: `SC-AUD-002`
- Result: the worktree configuration publishes database target `5432` on host port `55433`. The existing healthy `app-pgsql-1` container already owns `0.0.0.0:55433`, so a second isolated Compose project cannot bind that port concurrently. See `SC-AUD-002`.

## Runtime Versions

### PHP

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app php --version`
- Timestamp: `2026-06-14T10:35:17.815+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted
- Exit code: `0`
- Duration: `1.086s`
- Finding IDs: none
- Result: PHP `8.3.31` CLI with Zend OPcache.

### Composer

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app composer --version`
- Timestamp: `2026-06-14T10:35:18.906+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted
- Exit code: `0`
- Duration: `2.279s`
- Finding IDs: none
- Result: Composer `2.10.1` using PHP `8.3.31`.

### PostgreSQL client

- Command: `docker exec -i app-pgsql-1 psql --version`
- Timestamp: `2026-06-14T10:35:21.225+03:00`
- Environment: existing PostgreSQL container
- Exit code: `0`
- Duration: `0.270s`
- Finding IDs: none
- Result: PostgreSQL client `16.14`.

## Dependencies

### npm lockfile tracking

- Command: `git ls-files package-lock.json`
- Timestamp: `2026-06-14T10:41:45.589+03:00`
- Environment: host PowerShell in the audit worktree
- Exit code: `0`
- Duration: `0.027s`
- Finding IDs: `SC-AUD-001`
- Result: no output; `package-lock.json` is not tracked by Git.

### Composer manifest validation

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app composer validate --strict`
- Timestamp: `2026-06-14T10:35:21.498+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted
- Exit code: `0`
- Duration: `2.116s`
- Finding IDs: none
- Result: `composer.json` is valid under strict validation.

### Direct Composer dependencies

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app composer show --direct`
- Timestamp: `2026-06-14T10:35:23.617+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted
- Exit code: `0`
- Duration: `1.699s`
- Finding IDs: none
- Result: 11 direct packages were installed: `fakerphp/faker 1.24.1`, `filament/filament 5.6.6`, `laravel/framework 12.61.1`, `laravel/pail 1.2.7`, `laravel/pint 1.29.1`, `laravel/sail 1.62.0`, `laravel/tinker 2.11.1`, `mockery/mockery 1.6.12`, `nunomaduro/collision 8.9.4`, `phpunit/phpunit 11.5.55`, and `smalot/pdfparser 2.12.5`.

### Laravel application summary

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app php artisan about`
- Timestamp: `2026-06-14T10:35:25.325+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted; ephemeral test key supplied
- Exit code: `0`
- Duration: `23.547s`
- Finding IDs: none
- Result: Laravel `12.61.1`, PHP `8.3.31`, Composer `2.10.1`, Filament `5.6.6`, and Livewire `4.3.1`. The local environment reported debug enabled, PostgreSQL, database-backed cache/queue/session, UTC timezone, uncached config/events/routes, cached views, and an unlinked public storage path.

## Routes and Migrations

### Application routes

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app php artisan route:list --except-vendor`
- Timestamp: `2026-06-14T10:35:48.903+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted and connected to `app_default`
- Exit code: `0`
- Duration: `11.188s`
- Finding IDs: none
- Result: Laravel listed `84` non-vendor routes.

### Migration status

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app php artisan migrate:status`
- Timestamp: `2026-06-14T10:36:00.093+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted; database host `pgsql` on `app_default`
- Exit code: `0`
- Duration: `10.801s`
- Finding IDs: none
- Result: all `19` listed migrations had status `Ran`; batches `1` and `2` were present.

## Automated Tests

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app php artisan test --compact`
- Timestamp: `2026-06-14T10:36:23.010+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted; `phpunit.xml` test database `skycenter_app_test` on `pgsql`
- Exit code: `0`
- Duration: `153.567s` wall clock; PHPUnit reported `142.18s`
- Finding IDs: none
- Result: `166` tests passed with `454` assertions and no failures.

## Formatting

- Command: `docker run --rm --network app_default -e APP_KEY=<ephemeral-test-key> -v <audit-worktree>:/var/www/html -w /var/www/html app-app vendor/bin/pint --test`
- Timestamp: `2026-06-14T10:39:12.330+03:00`
- Environment: one-off `app-app` image with the audit worktree mounted
- Exit code: `1`
- Duration: `10.084s`
- Finding IDs: `SC-AUD-003`
- Result: Pint checked `302` files and reported `78` style issues across application and test files. No files were changed. See `SC-AUD-003`.

## Frontend Build

### Clean npm install

- Command: `npm ci`
- Timestamp: `2026-06-14T10:39:22.543+03:00`
- Environment: host Node.js/npm in the audit worktree; `package-lock.json` absent
- Exit code: `1`
- Duration: `1.422s`
- Finding IDs: `SC-AUD-001`
- Result: npm returned `EUSAGE`: `npm ci` requires an existing `package-lock.json` or compatible `npm-shrinkwrap.json`. No lockfile was created. See `SC-AUD-001`.

### Production asset build

- Command: `npm run build`
- Timestamp: `2026-06-14T10:39:23.968+03:00`
- Environment: host Node.js/npm in the audit worktree using pre-existing local `node_modules` prepared with `npm install`
- Exit code: `0`
- Duration: `2.720s`; Vite reported `1.39s`
- Finding IDs: `SC-AUD-001`
- Result: Vite `7.3.5` transformed `55` modules and emitted the production manifest, CSS, and JavaScript under ignored `public/build/` paths.

## Reproducibility Blockers

- `SC-AUD-001`: the repository does not contain a committed npm lockfile, so the required clean-install command fails and the successful build depends on previously prepared local dependencies.
- `SC-AUD-002`: the Compose configuration fixes PostgreSQL host port `55433`, which is already occupied by the existing application stack. Worktree code therefore required a one-off mounted `app-app` container rather than an independently started Compose project.
- The ephemeral application key was supplied only to one-off test containers and is intentionally omitted from this document.

## Baseline Findings

| ID | Severity | Class | Summary | Baseline evidence |
|---|---|---|---|---|
| `SC-AUD-001` | Medium | operational-gap | Clean npm installation is not reproducible because no lockfile is committed. | `npm ci` exited `1`; `npm run build` succeeded only with pre-existing `node_modules`. |
| `SC-AUD-002` | Low | operational-gap | A second isolated Compose project cannot bind the configured PostgreSQL host port while the existing stack is running. | Compose publishes `55433`; `app-pgsql-1` already owns that host port. |
| `SC-AUD-003` | Low | maintainability | The repository does not pass its Pint formatting check. | Pint exited `1` with `78` style issues in `302` checked files. |
