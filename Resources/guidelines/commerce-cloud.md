# Magento 2 — Adobe Commerce Cloud Guidelines

## Project structure

Adobe Commerce Cloud projects use a Git-based deployment model:

- `.magento/` — environment and service configuration
  - `services.yaml` — declares services (MySQL, Redis, Elasticsearch/OpenSearch, RabbitMQ)
  - `routes.yaml` — HTTP routing rules
  - `env.php` equivalents are injected at deploy time; do not commit credentials
- `.magento.env.yaml` — per-environment variables and deploy hooks (managed by `ece-tools`)
- `.magento.app.yaml` — application container config (PHP version, hooks, disk, mounts, crons)

## Deploy pipeline

Cloud runs three phases: **build → deploy → post-deploy**.

- `hooks.build` — runs during the build phase (no services); compile DI, deploy static content here.
- `hooks.deploy` — runs with services available; run `setup:upgrade`, `cache:flush`.
- `hooks.post_deploy` — background tasks after traffic is restored; warm caches.

The `ece-tools` package manages the standard pipeline. Override phases by adding commands to `.magento.app.yaml` hooks, but avoid duplicating what `ece-tools` already does.

## Environment variables

Use `MAGENTO_CLOUD_VARIABLES` or `ece-tools` env vars rather than hardcoding in `app/etc/env.php`. Common ones:

- `CACHE_CONFIGURATION` — Redis cache config
- `SESSION_CONFIGURATION` — Redis session config  
- `SEARCH_CONFIGURATION` — Elasticsearch/OpenSearch endpoint
- `DATABASE_CONFIGURATION` — DB credentials (auto-injected by platform)

## Crons

Declare crons in `.magento.app.yaml` under `crons:`. Do not rely on system crontabs. Use Magento's cron framework (`bin/magento cron:run`) rather than calling PHP scripts directly.

## Staging and production

- Never push directly to production. Use `git push origin master` only for production; use `integration` or `staging` branches first.
- Use Cloud CLI (`magento-cloud`) or the Cloud Console to manage environment variables, SSH access, and snapshots.
- Snapshots before risky deploys: `magento-cloud snapshot:create`.

## Redis and cache tiers

Adobe Commerce Cloud typically uses two Redis instances: one for default/page cache, one for sessions. Ensure `app/etc/env.php` (or environment-injected config) maps them correctly. Mixing session and cache on the same Redis instance leads to cache evictions killing sessions.
