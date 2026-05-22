---
name: plugin-observer-recipes
description: Guide the choice between plugin, observer, and preference; generate the right boilerplate
---

# Skill: Plugin, Observer, and Preference Recipes

## When to use

When a user asks to "intercept a method", "hook into X", "listen to an event", "extend class Y", or "modify the behavior of Z".

## Decision guide

| Goal | Mechanism | Reason |
|------|-----------|--------|
| Modify method inputs or return value | **Plugin** (`before`/`after`/`around`) | Composable; multiple plugins can coexist |
| React to a business event | **Observer** | Decoupled; fire-and-forget |
| Replace the entire class | **Preference** | Last resort; use only when plugins cannot reach the target |
| Add new methods to an interface | **Extension attributes** + `extension_attributes.xml` | API-safe; does not break interfaces |

## Plugin boilerplate

`etc/di.xml`:
```xml
<type name="Magento\Catalog\Model\Product">
    <plugin name="{vendor}_{module}_product" type="{Vendor}\{Module}\Plugin\ProductPlugin" sortOrder="10"/>
</type>
```

`Plugin/ProductPlugin.php`:
```php
<?php
declare(strict_types=1);
namespace {Vendor}\{Module}\Plugin;

class ProductPlugin
{
    public function afterGetName(\Magento\Catalog\Model\Product $subject, string $result): string
    {
        return $result . ' (modified)';
    }
}
```

## Observer boilerplate

`etc/frontend/events.xml`:
```xml
<config>
    <event name="catalog_product_load_after">
        <observer name="{vendor}_{module}_product_load"
                  instance="{Vendor}\{Module}\Observer\AfterProductLoad"/>
    </event>
</config>
```

`Observer/AfterProductLoad.php`:
```php
<?php
declare(strict_types=1);
namespace {Vendor}\{Module}\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class AfterProductLoad implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        // your logic here
    }
}
```

## Notes

- `around` plugins wrap the entire method call, including its own plugins below them. Use only when you must conditionally skip the original method.
- Do not declare preferences for classes you do not own if a plugin can achieve the same result.
- Observers cannot return values or modify arguments; they react to data via the event object.
