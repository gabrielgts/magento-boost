# Luma / Blank Theme — Developer Guidelines

## Tech stack

| Component | Technology |
|-----------|-----------|
| Styling | **Less** (compiled by Magento's Less pipeline) |
| JavaScript | **RequireJS** + **Knockout.js** for reactive UI |
| Build | `bin/magento setup:static-content:deploy` (production) |

## Theme structure

```
app/design/frontend/{Vendor}/{theme}/
  theme.xml                       → <parent>Magento/luma</parent> (or Magento/blank)
  etc/view.xml                    → image sizes, media config
  web/css/source/
    _theme.less                   → override Less variables
    _extend.less                  → add rules without overriding
  {Vendor_Module}/
    templates/                    → template overrides
    layout/                       → layout XML overrides
    web/                          → JS/CSS/images
```

## Less customization

```less
// _theme.less — override variables before Luma's defaults
@primary__color: #your-brand;
@font-size__base: 16px;

// _extend.less — add rules
.your-selector { ... }
```

Never edit `lib/web/css/` or files in `vendor/magento/` — always override in the child theme.

## JavaScript — RequireJS

```javascript
// requirejs-config.js — declare mixins or path aliases
var config = {
    map: { '*': { 'myComponent': 'Vendor_Module/js/component' } },
    config: { mixins: { 'Magento_Checkout/js/model/quote': { 'Vendor_Module/js/mixin': true } } }
};

// AMD component
define(['jquery', 'uiComponent', 'ko'], function($, Component, ko) {
    'use strict';
    return Component.extend({
        defaults: { template: 'Vendor_Module/my-template' },
        initialize: function() { this._super(); this.myObs = ko.observable(); }
    });
});
```

## Knockout.js

Used for reactive cart, mini-cart, checkout, and customer sections:

```html
<!-- Template binding -->
<div data-bind="scope: 'checkout'">
    <span data-bind="text: grandTotal()"></span>
</div>

<!-- JS — customer section data -->
require(['Magento_Customer/js/customer-data'], function(customerData) {
    var cart = customerData.get('cart');
    cart.subscribe(function(data) { console.log(data.subtotal); });
});
```

## UI Components

Grid and form components in adminhtml use the UI Component system (XML + JS). Key points:
- Declared in `view/adminhtml/ui_component/*.xml`
- JS files in `view/adminhtml/web/js/`
- Use `Magento_Ui/js/form/element/*` as base types

## Static content deployment

```bash
# Developer mode — files resolved on-the-fly, no deploy needed
bin/magento deploy:mode:set developer

# Production — must deploy after any frontend change
bin/magento setup:static-content:deploy -f en_US
bin/magento cache:flush
```

## Performance and caching

- Do not add `cacheable="false"` to layout unless the block truly cannot be cached — it makes the entire page uncacheable.
- Use `\Magento\Framework\App\Http\Context` to vary the FPC for customer groups or store views.
- ESI (Varnish) requires the block to be served via a separate request — use `\Magento\PageCache\Model\DepersonalizeChecker` to strip personal data before FPC stores the response.
