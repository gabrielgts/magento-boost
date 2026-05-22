# magento-boost

AI-powered development tools for Magento 2 — inspired by [laravel/boost](https://github.com/laravel/boost).

`magento-boost` wires your AI coding agent (Claude Code, Codex, Cursor, Copilot, Gemini, Junie) into your Magento 2 project with:

- **An MCP server** backed by [n98-magerun2](https://github.com/netz98/n98-magerun2) so your AI can run `cache:flush`, query config, inspect indexers, run DB queries, list modules, and more — directly from the chat.
- **Magento-specific guidelines** merged into `CLAUDE.md`, `AGENTS.md`, etc. — module anatomy, DI rules, plugin vs observer vs preference, declarative schema, compile/cache workflow, common footguns.
- **Skills** for common Magento workflows: scaffold a module, write a plugin/observer, add a DB column.

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

## Usage

### Interactive setup

```bash
bin/magento boost:install
```

The command will:
1. Detect your existing AI agent configs
2. Detect your magerun2 binary and version
3. Ask which IDE(s) and AI agent(s) to configure
4. Ask which guideline packs and skills to install
5. Optionally register the MCP server with a safe tool allowlist
6. Write all config files and save your selections to `boost.json`

### Re-generate after guideline updates

```bash
bin/magento boost:update
```

Non-interactive — reads `boost.json` and regenerates all files.

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

All managed regions are wrapped in `<!-- magento-boost:guidelines:start/end -->` markers so re-runs replace only the Boost-managed content.

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

---

## Skills

| Skill | Triggers when AI is asked to… |
|---|---|
| `module-scaffolding` | "create a module", "scaffold a new module" |
| `plugin-observer-recipes` | "intercept method X", "hook into Y", "listen to event Z" |
| `db-schema-changes` | "add a column", "create a table", "store data for…" |

---

## License

MIT
