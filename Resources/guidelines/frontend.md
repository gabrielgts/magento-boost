# Magento 2 — Frontend Guidelines

## Theme structure

Themes live under `app/design/{area}/{Vendor}/{theme}/`:
- `theme.xml` — declares parent theme and title
- `registration.php` — registers with `ComponentRegistrar::THEME`
- `etc/view.xml` — image sizes and media configuration
- `web/` — CSS/Less/JS/images served as static content
- `{Vendor_Module}/` — module-scoped overrides (templates, layout, web assets)

Inherit from a parent theme (`<parent>Magento/luma</parent>` or `<parent>Hyva/default</parent>`) and only override what you change.

## Hyvä themes

If `hyva-themes/magento2-theme-module` is present in `composer.lock`, the project uses Hyvä:

- **No Knockout.js or RequireJS.** All interactivity is Alpine.js + Tailwind CSS.
- Templates are `.phtml` with Alpine directives (`x-data`, `x-on:click`, `x-show`, etc.).
- Event bus: `window.dispatchEvent(new CustomEvent('...'))` / `@event-name.window` in Alpine.
- Cart and customer data come from a REST call to Hyvä's cart API, not `customerData.get('cart')`.
- Use `$hyva` utility object available in all `.phtml` files for formatting, config, and escaping helpers.
- Tailwind CSS: add custom classes in `tailwind.config.js`; run `npm run build-css` or equivalent for production builds. **Never add inline styles** — use utility classes.
- Checkout typically requires Hyvä Checkout or a compatible replacement (standard Magento checkout uses Knockout).

## Standard Luma / Blank themes

- Frontend uses RequireJS (`require`, `define`) for JavaScript modules.
- Knockout.js for reactive UI (`data-bind`, `ko.observable`).
- Less for stylesheets — compiled by Magento's Less pipeline. Extend via `_module.less` / `_extend.less`.
- Customer section data: `require(['Magento_Customer/js/customer-data'], function(customerData) { ... })`.

## Layout XML best practices

- Never copy the entire layout file — use `referenceBlock` or `referenceContainer`.
- To remove a block: `<referenceBlock name="foo" remove="true"/>`.
- To move a block: `<move element="foo" destination="bar" after="-"/>`.
- Page layout handles: `default`, `catalog_product_view`, `checkout_cart_index`, etc.

## Static content and assets

- Place JS in `view/{area}/web/js/`, CSS/Less in `view/{area}/web/css/`.
- Images: `view/{area}/web/images/`; reference via `$block->getViewFileUrl('images/foo.png')`.
- After adding or changing static assets in production: `bin/magento setup:static-content:deploy`.
- In developer mode, static assets are resolved on-the-fly from symlinks; no deploy needed.
- Use `requirejs-config.js` for RequireJS path aliases and mixins.

## Performance

- Use full-page cache (Varnish or built-in) in production — avoid adding uncacheable blocks to cached pages.
- Mark a block as non-cacheable only when truly necessary: `cacheable="false"` in layout XML renders the entire page uncacheable.
- Use `\Magento\Framework\App\Http\Context` for vary-cache contexts (e.g. customer group, currency).
