# utils-hyperf

Utilities for Hyperf 3.1 applications.

This package provides common development utilities for Hyperf projects, including:

- Base Repository abstraction
- Batch insert / upsert helpers
- Audit log utilities
- Model generator
- Repository / ValueObject / Validator generators
- API spec page generator
- Dependency file generator

---

## Requirements

- PHP 8.3+
- Hyperf 3.1+
- Composer

---

## Installation

```bash
composer require atellitech/utils-hyperf
````

If you are developing this package locally:

```bash
composer create-project hyperf/component-creator
```

---

## Publish Config

```bash
php bin/hyperf.php vendor:publish atellitech/utils-hyperf
```

This will publish the config file to:

```text
config/autoload/audit_log.php
```

---

# Core Repository

`AbstractRepository` provides a reusable base repository for Hyperf models.

It is designed for common CRUD operations, query builder access, transaction handling, batch insert, upsert, and MySQL duplicate key update.

## Basic Usage

Create a repository class:

```php
<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Model\User;
use AtelliTech\Hyperf\Utils\Core\AbstractRepository;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    protected string $modelClass = User::class;
}
```

Use the repository:

```php
$user = $userRepository->create([
    'name' => 'Eric',
    'email' => 'eric@example.com',
]);
```

---

## Available Methods

### Create

```php
public function create(array $data): Model
```

Creates a new record.

```php
$user = $userRepository->create([
    'name' => 'Eric',
    'email' => 'eric@example.com',
]);
```

The new version checks the result of `save()` and throws `RuntimeException` if the record cannot be saved.

---

### Update

```php
public function update(mixed $id, array $data): Model
```

Updates a record by primary key.

```php
$user = $userRepository->update($id, [
    'name' => 'New Name',
]);
```

---

### Update With Changes

```php
public function updateWithChanges(mixed $id, array $data): array
```

Updates a record and returns dirty fields and old/new changes.

```php
$result = $userRepository->updateWithChanges($id, [
    'status' => 'active',
]);

$user = $result['model'];
$dirty = $result['dirty'];
$changes = $result['changes'];
```

Example result:

```php
[
    'model' => $user,
    'dirty' => [
        'status' => 'active',
    ],
    'changes' => [
        'status' => [
            'old' => 'inactive',
            'new' => 'active',
        ],
    ],
]
```

This is useful for:

* Audit logs
* Change tracking
* Debugging
* Data update notifications

---

### Delete

```php
public function delete(mixed $id): bool
```

Deletes a record by primary key.

```php
$userRepository->delete($id);
```

---

### Find One

```php
public function findOne(mixed $pk): ?Model
```

Finds a record by primary key.

```php
$user = $userRepository->findOne($id);

if ($user === null) {
    // not found
}
```

---

### Find One Or Fail

```php
public function findOneOrFail(mixed $pk): Model
```

Finds a record by primary key.
If the record does not exist, an exception will be thrown.

```php
$user = $userRepository->findOneOrFail($id);
```

---

### Query Builder

```php
public function query(): Builder
```

Creates a query builder for the model.

```php
$users = $userRepository->query()
    ->where('status', 'active')
    ->orderByDesc('id')
    ->get();
```

For backward compatibility, existing projects may still use:

```php
$userRepository->find()
```

New code should prefer:

```php
$userRepository->query()
```

---

### Transaction

```php
public function transaction(Closure $callback)
```

Runs a database transaction.

```php
$userRepository->transaction(function () use ($userRepository) {
    $userRepository->create([
        'name' => 'Eric',
    ]);
});
```

The transaction will use the model connection if the model has a custom connection.

---

# Batch Insert

## Insert

```php
public function insert(array $rows): bool
```

Batch inserts records.

```php
$userRepository->insert([
    [
        'name' => 'Eric',
        'email' => 'eric@example.com',
    ],
    [
        'name' => 'John',
        'email' => 'john@example.com',
    ],
]);
```

Important notes:

```text
insert() does not trigger model events.
insert() does not apply fillable / guarded rules.
insert() does not automatically handle timestamps.
```

---

## Insert Chunks

```php
public function insertChunks(array $rows, int $chunkSize = 500): bool
```

Batch inserts records by chunks.

```php
$userRepository->insertChunks($rows, 500);
```

This is useful for large imports or sync jobs.

---

## Insert With Timestamps

```php
public function insertWithTimestamps(array $rows): bool
```

Adds `created_at` and `updated_at` automatically before insert.

```php
$userRepository->insertWithTimestamps([
    [
        'name' => 'Eric',
        'email' => 'eric@example.com',
    ],
]);
```

---

## Insert Chunks With Timestamps

```php
public function insertChunksWithTimestamps(array $rows, int $chunkSize = 500): bool
```

Batch inserts records by chunks and automatically adds timestamps.

```php
$userRepository->insertChunksWithTimestamps($rows, 500);
```

---

# Create Or Update

## Update Or Create

```php
public function updateOrCreate(array $attributes, array $values): Model
```

Creates or updates one record.

```php
$user = $userRepository->updateOrCreate(
    [
        'email' => 'eric@example.com',
    ],
    [
        'name' => 'Eric',
        'status' => 'active',
    ]
);
```

This method is suitable for small amounts of data.

For large sync jobs, prefer `upsert()` or `insertChunksOnDuplicateKeyUpdateWithTimestamps()`.

---

## Upsert

```php
public function upsert(array $rows, array $uniqueBy, array $updateColumns): int
```

Batch creates or updates records.

```php
$userRepository->upsert(
    [
        [
            'email' => 'eric@example.com',
            'name' => 'Eric',
            'status' => 'active',
        ],
        [
            'email' => 'john@example.com',
            'name' => 'John',
            'status' => 'inactive',
        ],
    ],
    ['email'],
    ['name', 'status', 'updated_at']
);
```

---

# MySQL ON DUPLICATE KEY UPDATE

## Insert On Duplicate Key Update

```php
public function insertOnDuplicateKeyUpdate(array $rows, array $updateColumns): int
```

Runs MySQL `INSERT ... ON DUPLICATE KEY UPDATE`.

```php
$userRepository->insertOnDuplicateKeyUpdate(
    [
        [
            'email' => 'eric@example.com',
            'name' => 'Eric',
            'status' => 'active',
        ],
    ],
    [
        'name',
        'status',
        'updated_at',
    ]
);
```

This method depends on MySQL `PRIMARY KEY` or `UNIQUE KEY`.

Example:

```sql
ALTER TABLE users
ADD UNIQUE KEY uk_users_email (email);
```

---

## Insert Chunks On Duplicate Key Update

```php
public function insertChunksOnDuplicateKeyUpdate(
    array $rows,
    array $updateColumns,
    int $chunkSize = 500
): int
```

Runs MySQL duplicate key update by chunks.

```php
$userRepository->insertChunksOnDuplicateKeyUpdate(
    $rows,
    ['name', 'status', 'updated_at'],
    500
);
```

---

## Insert Chunks On Duplicate Key Update With Timestamps

```php
public function insertChunksOnDuplicateKeyUpdateWithTimestamps(
    array $rows,
    array $updateColumns,
    int $chunkSize = 500
): int
```

Adds `created_at` and `updated_at`, then runs MySQL duplicate key update by chunks.

```php
$userRepository->insertChunksOnDuplicateKeyUpdateWithTimestamps(
    $rows,
    ['name', 'status', 'updated_at'],
    500
);
```

---

# Repository Upgrade Guide

## Summary

The new repository version adds:

* `query()`
* `findOneOrFail()`
* `updateWithChanges()`
* `insert()`
* `insertChunks()`
* `insertWithTimestamps()`
* `insertChunksWithTimestamps()`
* `updateOrCreate()`
* `upsert()`
* `insertOnDuplicateKeyUpdate()`
* `insertChunksOnDuplicateKeyUpdate()`
* `insertChunksOnDuplicateKeyUpdateWithTimestamps()`

It also improves:

* Save failure handling
* PHPStan compatibility
* Model connection-aware transaction
* Batch insert validation
* Dirty data tracking

---

## Backward Compatibility Notes

### `find()` and `query()`

Older versions use:

```php
$repo->find()
```

New code should use:

```php
$repo->query()
```

To avoid breaking existing projects, `find()` should be kept as an alias:

```php
public function find(): Builder
{
    return $this->query();
}
```

---

### `create()` Transaction Parameter

Older versions supported:

```php
$repo->create($data, true);
```

The recommended new style is:

```php
$repo->transaction(function () use ($repo, $data) {
    return $repo->create($data);
});
```

If your projects still use `create($data, true)`, keep the old signature temporarily:

```php
public function create(array $data, bool $useTransaction = false): Model
```

Recommended migration:

```text
Short term: keep create(array $data, bool $useTransaction = false)
Long term: move transaction control to UseCase / Service
```

---

### Not Found Exception

Older versions throw:

```php
InvalidArgumentException
```

The new version may use:

```php
RecordNotFoundException
```

Please check existing exception handling code.

If you need safer migration, make `RecordNotFoundException` extend `InvalidArgumentException`.

---

## Upgrade Checklist

### 1. Search for `create($data, true)`

```bash
grep -R "create(.*true" app/
```

Recommended replacement:

```php
$repo->transaction(function () use ($repo, $data) {
    return $repo->create($data);
});
```

---

### 2. Search for `find()`

```bash
grep -R "->find()" app/
```

If many places use `find()`, do not remove it.
Keep it as an alias for `query()`.

---

### 3. Check exception handling

Search:

```bash
grep -R "InvalidArgumentException" app/
```

If existing code catches `InvalidArgumentException` for missing records, update it carefully.

---

### 4. Check batch insert data shape

All rows must have the same columns.

Invalid:

```php
[
    [
        'name' => 'A',
        'status' => 'active',
    ],
    [
        'name' => 'B',
    ],
]
```

Valid:

```php
[
    [
        'name' => 'A',
        'status' => 'active',
    ],
    [
        'name' => 'B',
        'status' => null,
    ],
]
```

---

### 5. Check unique keys before duplicate key update

Before using:

```php
insertChunksOnDuplicateKeyUpdateWithTimestamps()
```

Make sure your table has the correct unique key.

Example:

```sql
ALTER TABLE users
ADD UNIQUE KEY uk_users_email (email);
```

---

# Model Generator

This generator creates a model class by table name.

## Usage

```bash
php bin/hyperf.php at:gen:model [--disable-event-dispatcher] [--] <table> [<namespace> [<path> [<connection>]]]
```

## Arguments

### table

Table name.

### namespace

Namespace of the generated model class.

### path

Path of the generated model class file.

### connection

Database component ID.

---

# Repository, ValueObject and Validator Generator

Generate repository, value object, or validator by table and output files into a specific domain.

## Usage

```bash
php bin/hyperf.php at:gen:[repo|vo|validator] [--disable-event-dispatcher] [--] <table> [<domain> [<connection>]]
```

## Arguments

### table

Table name.

### domain

DDD domain name.

### connection

Database component ID.

---

# API Spec Page Generator

Generate API documentation page by namespace and path.

## Usage

```bash
php bin/hyperf.php at:gen:apidoc [options] [--] [<namespace> [<path>]]
```

## Arguments

### namespace

Class namespace.

### path

Generated file path.

---

# Dependency File Generator

Generate dependency files for Repository and Service classes by scanning a specific path.

The default scan path is:

```text
{root path}/app
```

The generated file is:

```text
config/autoload/autoload_dependencies.php
```

---

## Step 1: Add generated dependency file to `config/autoload/dependencies.php`

```php
<?php

declare(strict_types=1);

$src = __DIR__ . '/autoload_dependencies.php';

$autoDependencies = [];

if (file_exists($src)) {
    $autoDependencies = require $src;
}

return array_merge((array) $autoDependencies, [
    // other manual dependencies
]);
```

---

## Step 2: Execute command

```bash
php bin/hyperf.php at:gen:di [options] [--] [<path>]
```

## Arguments

### path

Path to scan.

---

## Step 3: Add script to `composer.json`

```json
{
    "scripts": {
        "di": "php bin/hyperf.php at:gen:di"
    }
}
```

Then run:

```bash
composer di
```

---

# Audit Log

This package provides audit log support through events and listeners.

## Publish Config

```bash
php bin/hyperf.php vendor:publish atellitech/utils-hyperf
```

Config file:

```text
config/autoload/audit_log.php
```

## Example Config

```php
<?php

declare(strict_types=1);

return [
    'events' => [
        // App\Event\UserUpdatedEvent::class,
    ],
];
```

## Event Interface

Audit log events should implement:

```php
AtelliTech\Hyperf\Utils\Listener\AuditLogEventInterface
```

Expected event methods include:

```php
getEventName()
getTableName()
getTableId()
getUserId()
getOriginalValues()
getNewValues()
```

---

# Development

## PHPStan

If PHPStan cannot find the Hyperf `BASE_PATH` constant, add a PHPStan bootstrap file.

Create:

```text
phpstan-bootstrap.php
```

```php
<?php

declare(strict_types=1);

if (! defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
```

Then add to `phpstan.neon`:

```neon
parameters:
    bootstrapFiles:
        - phpstan-bootstrap.php
```

---

# Changelog

## Added

* Added `query()` as the preferred query builder method.
* Added `findOneOrFail()`.
* Added `updateWithChanges()`.
* Added batch insert support.
* Added chunk insert support.
* Added timestamp insert support.
* Added `updateOrCreate()`.
* Added `upsert()`.
* Added MySQL `ON DUPLICATE KEY UPDATE` helpers.
* Added model connection-aware transaction handling.
* Added PHPStan-friendly helper methods.

## Changed

* `create()` now checks whether `save()` succeeds.
* `update()` now uses `findOneOrFail()` internally.
* `delete()` now uses `findOneOrFail()` internally.
* `transaction()` now uses model connection when available.
* Batch insert methods now validate that all rows have the same columns.

## Deprecated

* `find()` is kept for backward compatibility.
* New code should prefer `query()`.

## Potential Breaking Changes

* Removing `create(array $data, bool $useTransaction = false)` may break existing calls using `create($data, true)`.
* Removing `find()` may break existing code.
* Changing not-found exception type may affect existing exception handling.

---

# Recommended Architecture

Repository should focus on data access.

Recommended application structure:

```text
Controller
  ↓
UseCase / Service / Handler
  ↓
Repository
  ↓
Model
```

Transaction boundaries should usually be controlled by UseCase / Service.

Example:

```php
$this->userRepository->transaction(function () use ($rows) {
    $this->userRepository->insertChunksWithTimestamps($rows);
});
```

Avoid putting complex business workflows inside repositories.
