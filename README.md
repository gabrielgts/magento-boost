# magento-boost

AI-powered development tools for Magento 2 — inspired by [laravel/boost](https://github.com/laravel/boost).

`magento-boost` wires your AI coding agent (Claude Code, Codex, Cursor, Copilot, Gemini, Junie) into your Magento 2 project with:

- **An MCP server** backed by [n98-magerun2](https://github.com/netz98/n98-magerun2) so your AI can run `cache:flush`, query config, inspect indexers, run DB queries, list modules, and more — directly from the chat.
- **Magento-specific guidelines** merged into `CLAUDE.md`, `AGENTS.md`, etc. — module anatomy, DI rules, plugin vs observer vs preference, declarative schema, compile/cache workflow, common footguns.
- **Theme-aware** — automatically detects Hyvä, Alpaca, or Luma and appends the right theme guidelines.
- **Project module inventory** — scans your installed custom and third-party modules and includes them in the AI context.
- **Skills** for common Magento workflows: scaffold a module, write a plugin/observer, add a DB column.
- **Extensible** — any Composer package can add its own guidelines and skills via a single `composer.json` declaration.

---

## Requirements

- Magento 2.4.x
- PHP 8.1+
- [n98-magerun2](https://github.com/netz98/n98-magerun2) **≥ 9.4.0** for MCP server support

## Installation

```bash
composer require --dev gtstudio/magento-boost
bin/magento setup:upgrade
```

If you do not already have magerun2:

```bash
composer require --dev n98/magerun2-dist:^9.4
```

---

## Usage

### Interactive setup

```bash
bin/magento boost:install
```

The command will:
1. Detect existing AI agent configs and the active theme
2. Discover any installed boost extension packages
3. Detect the magerun2 binary and version
4. Ask which AI agents to configure
5. Ask which guideline packs and skills to install
6. Ask which extensions to include (or exclude)
7. Optionally register the MCP server with a safe tool allowlist
8. Write all config files and save your selections to `boost.json`

### Re-generate after updates

```bash
bin/magento boost:update
```

Non-interactive — reads `boost.json`, re-discovers extensions, and regenerates all files. Safe to run after `composer update` or when guideline content changes.

### MCP server

The AI agent calls `boost:mcp` directly via stdio. You can also start it manually to test:

```bash
bin/magento boost:mcp
```

Pass `--preset=read-only|dev-safe|everything` to override the saved allowlist.

---

## What gets configured

| Agent | Config files written |
|---|---|
| Claude Code | `.mcp.json` or `claude mcp add`, `CLAUDE.md` |
| OpenAI Codex | `.mcp.json`, `AGENTS.md` |
| Cursor | `.cursor/mcp.json`, `.cursor/rules/magento-boost.mdc` |
| VS Code / Copilot | `.vscode/mcp.json`, `.github/copilot-instructions.md` |
| Gemini CLI | `.mcp.json`, `GEMINI.md` |
| Junie | `.mcp.json`, `.junie/guidelines.md` |

Shared:
- `.ai/guidelines/magento-boost/` — guideline markdown files
- `.ai/skills/` — YAML-frontmatter skill files
- `boost.json` — your selections (safe to commit)

All managed regions are wrapped in `<!-- magento-boost:guidelines:start/end -->` markers so re-runs replace only the Boost-managed content and preserve anything you write outside the markers.

---

## MCP tool allowlist presets

| Preset | Tools exposed |
|---|---|
| `read-only` | sys, config list, dev list, indexer status, cache list |
| `dev-safe` *(default)* | above + cache flush, indexer reindex, setup:upgrade |
| `everything` | All magerun commands — use with caution |

---

## Guideline packs

| Pack | Contents |
|---|---|
| `core` *(always)* | Module anatomy, DI, plugins, observers, compile/cache workflow, schema, coding standards |
| `frontend` | Theme structure, Hyvä vs Luma, layout XML, static content |
| `commerce-cloud` | `.magento/` config, deploy pipeline, ece-tools, Redis tiers |

Theme-specific guidelines (Hyvä, Alpaca, or Luma) are always appended automatically based on what is detected in `composer.lock`.

---

## Skills

| Skill | Triggers when AI is asked to… |
|---|---|
| `module-scaffolding` | "create a module", "scaffold a new module" |
| `plugin-observer-recipes` | "intercept method X", "hook into Y", "listen to event Z" |
| `db-schema-changes` | "add a column", "create a table", "store data for…" |

---

## Extensions

Extension packages let teams ship their own guidelines and skills alongside `magento-boost` — without forking the base package. When `boost:install` or `boost:update` runs, it scans `composer.lock` for any package that declares a `extra.magento-boost` block and merges its resources automatically.

### Creating an extension package

**1. Package structure**

```
your-vendor/magento-boost-rules/
  composer.json
  resources/boost/
    guidelines/
      project-conventions.md   ← merged into every agent's guideline file
    skills/
      custom-workflow/
        SKILL.md               ← copied to .ai/skills/custom-workflow/
```

**2. Declare resources in `composer.json`**

```json
{
    "name": "your-vendor/magento-boost-rules",
    "description": "Project-specific AI guidelines for magento-boost",
    "type": "library",
    "license": "MIT",
    "require": {
        "gtstudio/magento-boost": "^1.0"
    },
    "extra": {
        "magento-boost": {
            "guidelines": [
                "resources/boost/guidelines/project-conventions.md"
            ],
            "skills": [
                "resources/boost/skills/custom-workflow"
            ]
        }
    }
}
```

Paths in `guidelines` and `skills` are relative to the package root. Each `skills` entry must be a directory containing a `SKILL.md` file.

**3. Install it**

```bash
composer require --dev your-vendor/magento-boost-rules
bin/magento boost:update
```

`boost:update` re-discovers all extensions on every run, so adding or removing an extension package and running `composer update` followed by `boost:update` is all that is needed to keep the AI context current.

### What to put in a guideline file

A guideline file is plain Markdown. Write whatever you want your AI assistant to know about the project:

```markdown
# Project Conventions

## Module naming
All custom modules live under the `Acme` vendor namespace.
Follow the pattern `Acme_{Feature}` — no abbreviations.

## Deployment
This project deploys via GitHub Actions. Never push directly to `main`.
The staging environment is at staging.example.com.

## Third-party integrations
- Payment: uses AuthorizeNet via `paradoxlabs/authnetcim`. Do not add other payment gateways.
- Search: Elasticsearch via Smile ElasticSuite. Index names follow the pattern `magento2_{store}_{entity}`.
```

### Writing a SKILL.md

Skills guide the AI for specific recurring tasks. Use YAML frontmatter followed by Markdown instructions:

```markdown
---
name: create-acme-module
description: Scaffold a new Acme module following project conventions
---

# Skill: Create Acme Module

## When to use
When asked to "create a module", "add a feature", or "scaffold Acme_{Name}".

## Steps
1. Create `app/code/Acme/{Name}/registration.php`
2. Create `app/code/Acme/{Name}/etc/module.xml`
3. Run `bin/magento setup:upgrade`
4. Confirm with `bin/magento module:status Acme_{Name}`

## Conventions
- Use `declare(strict_types=1)` in every PHP file.
- Namespace: `Acme\{Name}\`
- All DB schema changes go in `etc/db_schema.xml` — never `InstallSchema`.
```

### Excluding an extension

During `boost:install` you can opt out of specific extensions when prompted. The excluded package names are saved in `boost.json` and respected on every subsequent `boost:update`:

```json
{
    "extensions": {
        "excluded": ["your-vendor/some-extension"]
    }
}
```

You can also edit `boost.json` directly and run `boost:update`.

---

## License

MIT
