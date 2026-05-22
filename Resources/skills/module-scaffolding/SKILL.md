---
name: module-scaffolding
description: Scaffold a new Magento 2 module with the minimum required files
---

# Skill: Magento 2 Module Scaffolding

## When to use

When a user asks to "create a module", "add a new Magento module", "scaffold a module", or "start a new module for Vendor_Name".

## What to generate

Always generate these files for a module named `{Vendor}/{Module}` located at `app/code/{Vendor}/{Module}/`:

### `registration.php`
```php
<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    '{Vendor}_{Module}',
    __DIR__
);
```

### `etc/module.xml`
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{Vendor}_{Module}" setup_version="1.0.0"/>
</config>
```

### `composer.json` (optional, for Composer-packaged modules)
```json
{
    "name": "{vendor}/{module-kebab}",
    "description": "...",
    "type": "magento2-module",
    "version": "1.0.0",
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "magento/framework": "*"
    },
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {
            "{Vendor}\\{Module}\\": ""
        }
    }
}
```

## Post-scaffold steps to tell the user

After generating the files:
1. Run `bin/magento setup:upgrade` to register the module.
2. Run `bin/magento module:status {Vendor}_{Module}` to verify it is enabled.
3. Optionally run `bin/magento setup:di:compile` if the module declares DI config.
