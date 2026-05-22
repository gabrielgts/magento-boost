---
name: db-schema-changes
description: Add or modify database tables and columns using Magento 2 declarative schema
---

# Skill: Database Schema Changes

## When to use

When a user asks to "add a column", "create a table", "add an index", "modify a database field", or "store data for X".

## Declarative schema (use this — not InstallSchema)

All DDL lives in `etc/db_schema.xml`. Magento computes the diff from the previous state automatically.

### Add a column to an existing table

```xml
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">
    <table name="catalog_product_entity" resource="default" engine="innodb">
        <column xsi:type="varchar" name="custom_field" nullable="true" length="255"
                comment="Custom field added by {Vendor}_{Module}"/>
    </table>
</schema>
```

### Create a new table

```xml
<table name="{vendor}_{module}_{entity}" resource="default" engine="innodb"
       comment="{Entity} table for {Vendor}_{Module}">
    <column xsi:type="int" name="entity_id" padding="10" unsigned="true" nullable="false"
            identity="true" comment="Entity ID"/>
    <column xsi:type="int" name="store_id" padding="5" unsigned="true" nullable="false"
            comment="Store ID"/>
    <column xsi:type="varchar" name="value" nullable="true" length="255" comment="Value"/>
    <column xsi:type="timestamp" name="created_at" on_update="false" nullable="false"
            default="CURRENT_TIMESTAMP" comment="Created At"/>
    <constraint xsi:type="primary" referenceId="PRIMARY">
        <column name="entity_id"/>
    </constraint>
    <index referenceId="{VENDOR}_{MODULE}_{ENTITY}_STORE_ID" indexType="btree">
        <column name="store_id"/>
    </index>
</table>
```

## Whitelist generation (required before production deploy)

After editing `db_schema.xml`, regenerate the whitelist so Magento tracks which columns your module owns:

```bash
bin/magento setup:db-declaration:generate-whitelist --module-name={Vendor}_{Module}
```

This updates `etc/db_schema_whitelist.json`. Commit both files together.

## Data patches

For DML (inserting/updating data), use a data patch:

`Setup/Patch/Data/AddDefaultValues.php`:
```php
<?php
declare(strict_types=1);
namespace {Vendor}\{Module}\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddDefaultValues implements DataPatchInterface
{
    public function __construct(private readonly ModuleDataSetupInterface $setup) {}

    public function apply(): void
    {
        $this->setup->startSetup();
        // $this->setup->getConnection()->insert(...)
        $this->setup->endSetup();
    }

    public static function getDependencies(): array { return []; }
    public function getAliases(): array { return []; }
}
```

Run `bin/magento setup:upgrade` to apply. Patches run exactly once; rerunning `setup:upgrade` is idempotent.

## After schema changes

```bash
bin/magento setup:upgrade               # applies schema and data patches
bin/magento setup:di:compile            # if you added new classes
bin/magento cache:flush                 # always safe to run after
```
