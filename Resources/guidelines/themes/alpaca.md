# Alpaca Theme (Snowdog) — Developer Guidelines

> **Note:** Alpaca reached end-of-life in April 2023 (last official Magento support: 2.4.4).
> Projects using Alpaca typically maintain a private fork or child theme. Treat upstream
> Alpaca as frozen — all changes go in the child theme.

## Tech stack

| Component | Technology |
|-----------|-----------|
| Styling | **SCSS** (not Less, not Tailwind) |
| JavaScript | **RequireJS** (standard Magento 2 pattern) |
| Build system | **Frontools** (`snowdog/frontools`) |
| Accessibility | WCAG AA compliant by design |

## Theme structure

Alpaca is a **parent theme**. Your project's custom theme inherits from it:

```
app/design/frontend/{Vendor}/{theme}/
  theme.xml               → <parent>Snowdog/alpaca</parent>
  web/css/source/         → SCSS overrides
  {Vendor_Module}/
    templates/            → template overrides
    web/css/source/       → module-specific SCSS
```

## SCSS customization

Override variables before importing Alpaca's styles:

```scss
// web/css/source/_variables.scss
$color__primary: #your-brand;
$font-size__base: 16px;

// web/css/source/_extend.scss — add styles without modifying Alpaca
.your-component { ... }
```

Run Frontools to compile SCSS:
```bash
npm run styles      # compile all themes
npm run browser-sync # dev mode with hot reload
```

Do **not** edit files inside `vendor/snowdog/theme-frontend-alpaca/` — changes will be lost on update. Always override in the child theme.

## JavaScript

Follows standard Magento 2 RequireJS patterns — same as Luma:

```javascript
// requirejs-config.js
var config = {
    map: { '*': { 'myAlias': 'Vendor_Module/js/component' } }
};

// component file
define(['jquery', 'Vendor_Module/js/dependency'], function($, dep) {
    'use strict';
    return function(config, element) { ... };
});
```

- Use `data-mage-init` or layout XML `<script>` for component initialization.
- Customer data: `require(['Magento_Customer/js/customer-data'], function(cd) { ... })` — same as Luma.

## Frontools build commands

```bash
cd vendor/snowdog/frontools
npm install                    # first time only
gulp styles --theme {themeName}  # compile SCSS
gulp watch --theme {themeName}   # watch mode
gulp eslint                      # JS linting
```

## Component overrides

To override an Alpaca component template:

1. Find the template in `vendor/snowdog/theme-frontend-alpaca/{Vendor_Module}/templates/`.
2. Copy it to `app/design/frontend/{Vendor}/{theme}/{Vendor_Module}/templates/`.
3. Edit the copy — Magento's fallback chain picks your version first.

## Layout XML

Works identically to standard Magento 2. Use layout handles, `referenceBlock`, `referenceContainer`. Alpaca-specific handles are documented in the Alpaca source.

## Admin configuration

Alpaca has a system config section for theme options (logos, colours in some versions). Check `Snowdog_AlpacaComponents` module for admin-configurable values before hardcoding in SCSS.
