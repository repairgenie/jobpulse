# JobPulse Database Upgrade Guide

## Overview

JobPulse supports dual-database backends, enabling it to run seamlessly on either **SQLite** (default local deployment) or **MySQL** (production deployment). Depending on how the application scales, administrators may need to migrate instances, add columns for new AI features, or synchronize schemas.

This guide outlines the precise steps for performing safe repository upgrades or database structure changes using `upgrade.php`.

## 1. Database Configuration
Before running any upgrades, ensure your environment is accurately targeting your intended database. Open `config.php` and verify these settings:

### For SQLite Deployments (Default)
```php
'DB_CONNECTION' => 'sqlite',
'DB_PATH' => __DIR__ . '/data/jobpulse.sqlite',
```

### For MySQL Deployments
```php
'DB_CONNECTION' => 'mysql',
'DB_HOST' => '127.0.0.1',
'DB_PORT' => '3306',
'DB_DATABASE' => 'jobpulse_production',
'DB_USERNAME' => 'root',
'DB_PASSWORD' => 'your_secure_password',
```

## 2. Performing the Upgrade Operations

The `upgrade.php` script is an idempotent utility that handles multiple layers of deployment logic automatically without destroying existing data.

### Running via Command Line (Recommended)
Open your terminal inside the root `jobpulse` folder and execute:
```bash
php upgrade.php
```

### Running via Web Browser
You can invoke the upgrades by visiting `http://your-app-domain.com/jobpulse/upgrade.php`. Be sure to delete this file later in production to prevent unintended access, or place it behind admin authentication.

### What the Script Accomplishes:
1. **Schema Initialization**: Checks that `jobs` and `resumes` table structures exist. If they do not, it evaluates `DB_CONNECTION` and executes either SQLite's `INTEGER PRIMARY KEY AUTOINCREMENT` logic or MySQL's `INT AUTO_INCREMENT` constraints dynamically.
2. **Column Probing (`ai_analysis`)**: Dynamically utilizes `PRAGMA table_info` (SQLite) or `SHOW COLUMNS` (MySQL) to determine if your latest structural columns exist. If they do not, it performs an in-place `ALTER TABLE` to inject the new `TEXT`/`LONGTEXT` columns seamlessly.
3. **Legacy JSON Migration**: If a legacy `history.json` file is detected from a pre-1.0 flat-file instance, the upgrade driver safely extracts the nodes, matches them via prepared statements to ensure no duplicates occur, and persists them accurately to your new relational database structure.

## 3. Best Practices & Emergency Fallbacks

> [!WARNING]
> While `upgrade.php` ensures data idempotency, structural alter statements naturally present risks in production environments with millions of rows.

1. **Always Backup Before Upgrade**: If using SQLite, take a physical copy of `data/jobpulse.sqlite`. If on MySQL, execute a standard `mysqldump` of the active schema.
2. **File Permissions**: The web-server user (e.g. `www-data` or `apache`) must maintain read/write permissions for both the `data/` directory and the `upgrade.php` file itself during the upgrade phase.
3. **Idempotence Constraint**: `upgrade.php` will deliberately skip injecting duplicate structural columns or duplicate historic JSON rows. You may run it multiple times safely.
