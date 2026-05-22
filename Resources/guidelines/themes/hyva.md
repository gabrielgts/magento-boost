# Hyvä Theme — Developer Guidelines

> **Tip:** Install [hyva-ai-tools](https://github.com/hyva-themes/hyva-ai-tools) for AI scaffolding skills
> specifically built for Hyvä (module generation, Alpine.js components, CMS blocks, Tailwind builds).

## Tech stack (replaces Luma entirely)

| Luma | Hyvä equivalent |
|------|----------------|
| RequireJS | ES modules or inline `<script>` |
| Knockout.js | **Alpine.js** (`x-data`, `x-on`, `x-show`, `x-bind`, `$dispatch`) |
| Less | **Tailwind CSS** (utility classes, no Less pipeline) |
| UI Components (most) | Hyvä custom components |

Hyvä requires **19+ GraphQL modules** (`CatalogGraphQl`, `CustomerGraphQl`, `QuoteGraphQl`, etc.). Disable Magento's native minification and bundling — Tailwind handles CSS.

## Alpine.js patterns

```html
<!-- Component with reactive state -->
<div x-data="{ open: false }">
    <button @click="open = !open">Toggle</button>
    <div x-show="open">Content</div>
</div>

<!-- Dispatching events across components -->
<button @click="$dispatch('toggle-cart', { open: true })">Open cart</button>

<!-- Listening at window level -->
<div x-on:toggle-cart.window="handleCartEvent($event.detail)">
```

Use `window.dispatchEvent(new CustomEvent('...', { detail: {} }))` for cross-component communication. Avoid direct DOM manipulation — use Alpine reactive state.

## Tailwind CSS

- Add custom classes in `tailwind.config.js`; never write inline `style=""` — use utility classes.
- Run `npm run build-css` (or project equivalent) after config changes.
- The `$hyva` object in `.phtml` templates provides formatting helpers: `$hyva->formatPrice()`, `$hyva->escapeHtml()`, `$hyva->getStoreConfig()`.

## Templates and layout

- Override templates in `{Theme}/{Vendor_Module}/templates/` (standard Magento fallback).
- Use `default.xml`, `catalog_product_view.xml`, etc. — layout XML works identically to Luma.
- No `data-bind` or `ko.*` — remove any Knockout bindings when porting from Luma.
- Page builder blocks: requires Hyvä-compatible page builder modules.

## JavaScript — what NOT to do

- Do NOT use `require([...], function() {})` — RequireJS is not loaded.
- Do NOT use `customerData.get('cart')` — use Hyvä's REST/GraphQL cart API instead.
- Do NOT add `.js` files to layout XML via `requirejs-config.js` — use `<script>` tags or Alpine components.

## Checkout

Standard Magento checkout uses Knockout and is **incompatible** with Hyvä without the Hyvä Checkout module (`hyva-themes/magento2-hyva-checkout`) or a compatible alternative (Checkout Suite, React Checkout). Confirm which checkout is installed before editing checkout templates.

## Magewire (optional)

If `magewire/magento2` is installed: server-driven reactive components (similar to Laravel Livewire). Components extend `\Magewire\Component`, declared in layout XML. Properties declared as `public $prop` are reactive; methods called from Alpine via `$wire.call('methodName')`.

## Hyvä-specific MCP / AI tools

Install [hyva-ai-tools](https://github.com/hyva-themes/hyva-ai-tools) for skills including:
- Scaffold new Hyvä modules and Alpine.js components
- Generate CMS components with Tailwind
- Run Tailwind builds via Docker commands

```bash
git clone https://github.com/hyva-themes/hyva-ai-tools
cd hyva-ai-tools && ./install.sh   # installs all skills
```
