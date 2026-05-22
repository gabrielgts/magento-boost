# Magento 2 — Core Development Guidelines

## Module anatomy

Every Magento 2 module lives under `app/code/{Vendor}/{Module}/` (or a Composer package) and requires:

- `registration.php` — registers the module with `ComponentRegistrar::MODULE`
- `etc/module.xml` — declares module name and setup version
- `etc/di.xml` — dependency injection configuration (plugins, preferences, virtual types, argument injection)

Routing (if the module has controllers):
- `etc/frontend/routes.xml` — frontend (`standard` router)
- `etc/adminhtml/routes.xml` — adminhtml (`admin` router)
- Controllers live in `Controller/` (frontend) or `Controller/Adminhtml/` (admin)

## Dependency injection rules

1. **Always use constructor injection.** Never call `ObjectManager::getInstance()` outside of factories, helpers that explicitly must do so (rare), and test fixtures.
2. **Prefer plugins over preferences.** A preference replaces the target class entirely and breaks if two modules both declare one. Plugins compose.
3. **Use observers for event-based decoupling.** Declare in `etc/{area}/events.xml`, implement `Magento\Framework\Event\ObserverInterface`.
4. **Use virtual types** (`<virtualType>`) to create variant instances of the same class without a new PHP file.
5. **Factories** (`SomeClassFactory`) are auto-generated for models and data objects. Inject the factory, not the class, when you need `new` instances.
6. **Proxies** (`SomeClass\Proxy`) are auto-generated to break circular dependencies or defer heavy object construction.

## Compile and cache workflow

| Action | When required |
|--------|--------------|
| `bin/magento setup:di:compile` | After adding/changing any `etc/di.xml`, new classes, constructor changes, or new plugins |
| `bin/magento setup:upgrade` | After adding/changing `etc/module.xml`, DB schema, or data patches |
| `bin/magento cache:flush` | After layout XML, template, config, or static content changes in dev mode |
| `bin/magento setup:static-content:deploy` | In production mode after any frontend asset change |

In developer mode (`MAGE_MODE=developer`), most caches can be left disabled. Never deploy with `MAGE_MODE=developer`.

The `generated/` directory holds auto-generated factories, interceptors, and proxies. **Never edit files in `generated/`.** Delete it when DI anomalies occur, then re-compile.

## Database schema (declarative)

Use `etc/db_schema.xml` for all DDL. Avoid `Setup/InstallSchema.php` and `Setup/UpgradeSchema.php` — they are deprecated.

- Column additions, index changes, and foreign keys are all declared in `db_schema.xml`.
- Data changes use `Setup/Patch/Data/` — implement `Magento\Framework\Setup\Patch\DataPatchInterface`. Patches run once and are tracked in `patch_list`.
- Generate a `db_schema_whitelist.json` with `bin/magento setup:db-declaration:generate-whitelist --module-name=Vendor_Module` before deploying schema changes to production.

## Plugins (interceptors)

```php
// etc/di.xml
<type name="Magento\Catalog\Model\Product">
    <plugin name="vendor_module_product_plugin" type="Vendor\Module\Plugin\ProductPlugin"/>
</type>
```

- `before{Method}` — modify input arguments; return array of args or null.
- `after{Method}` — modify return value; first param is subject, second is result.
- `around{Method}` — full control; must call `$proceed(...$args)` unless intentionally short-circuiting.
- Avoid `around` when `before`/`after` suffices — it has higher overhead and obscures the call chain.

## Observers

```php
// etc/frontend/events.xml
<event name="checkout_cart_add_product_complete">
    <observer name="vendor_module_after_add_to_cart" instance="Vendor\Module\Observer\AfterAddToCart"/>
</event>
```

Implement `execute(\Magento\Framework\Event\Observer $observer): void`. Do not throw exceptions from observers unless you intend to halt the event chain.

## ACL and admin configuration

- Declare ACL resources in `etc/acl.xml`.
- Admin menu entries in `etc/adminhtml/menu.xml`.
- System configuration sections/groups/fields in `etc/adminhtml/system.xml`; retrieve values via `\Magento\Framework\App\Config\ScopeConfigInterface`.

## Frontend layout and templates

- Layout XML: `view/{area}/layout/{handle}.xml` — use `referenceBlock` / `referenceContainer` to extend, not override.
- Templates: `view/{area}/templates/*.phtml` — use `$block->escapeHtml()`, `$block->escapeUrl()`, etc. Never echo unescaped user data.
- UI Components: `view/{area}/ui_component/*.xml` for grids and forms in adminhtml.

## Coding standards

- Follow `magento/magento-coding-standard` (based on PSR-2/PSR-12 with Magento extensions).
- Run: `vendor/bin/phpcs --standard=Magento2 app/code/Vendor/Module/`
- PHPStan at least level 5 for new code: `vendor/bin/phpstan analyse app/code/Vendor/Module/`
- Strict types: `declare(strict_types=1);` in every PHP file.
- No `echo`, `print_r`, `var_dump`, or `die` in production code.

## Common footguns

- **Two-step deploy**: always run `setup:upgrade` before `setup:di:compile` before `setup:static-content:deploy`.
- **Scope of `core_config_data`**: changes via `bin/magento config:set` do not take effect until `cache:flush`.
- **`var/cache/` and `var/page_cache/`**: flush these when layout or block output looks stale.
- **`generated/code/` stale interceptors**: if you rename a class, delete `generated/` and recompile.
- **Extension attributes**: register in `etc/extension_attributes.xml`; forget this and the attribute silently disappears from API responses.
- **Quote vs Order**: never load an order to read cart data; quote and order are separate entities.
- **Reindex**: `bin/magento indexer:reindex` is needed after bulk data changes; know which indexers your module affects.
