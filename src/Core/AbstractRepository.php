<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core;

use AtelliTech\Hyperf\Utils\Core\Exception\RecordNotFoundException;
use Closure;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template TModel of Model
 * @implements RepositoryInterface<TModel>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /**
     * @var class-string<TModel>
     */
    protected string $modelClass;

    public function __construct()
    {
        if (! isset($this->modelClass)) {
            throw new RuntimeException(static::class . ' must define $modelClass.');
        }

        if (! is_subclass_of($this->modelClass, Model::class)) {
            throw new InvalidArgumentException("{$this->modelClass} must extend " . Model::class);
        }
    }

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        $model = $this->newModel();

        $model->fill($data);

        if (! $model->save()) {
            throw new RuntimeException('Failed to create record.');
        }

        return $model;
    }

    /**
     * Update a record by primary key.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function update(mixed $id, array $data): Model
    {
        $model = $this->findOneOrFail($id);

        $model->fill($data);

        if ($model->isDirty() && ! $model->save()) {
            throw new RuntimeException("Failed to update record with ID {$id}.");
        }

        return $model->refresh();
        /** @var TModel $refreshed */
    }

    /**
     * Update a record and return dirty fields and old/new changes.
     *
     * @param array<string, mixed> $data
     * @return array{
     *     model: TModel,
     *     dirty: array<string, mixed>,
     *     changes: array<string, array{old: mixed, new: mixed}>
     * }
     */
    public function updateWithChanges(mixed $id, array $data): array
    {
        $model = $this->findOneOrFail($id);

        $model->fill($data);

        /**
         * Dirty data should be captured before save().
         *
         * @var array<string, mixed> $dirty
         */
        $dirty = $model->getDirty();

        $changes = [];

        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        if ($dirty !== [] && ! $model->save()) {
            throw new RuntimeException("Failed to update record with ID {$id}.");
        }

        /** @var TModel $refreshed */
        $refreshed = $model->refresh();

        return [
            'model' => $refreshed,
            'dirty' => $dirty,
            'changes' => $changes,
        ];
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(mixed $id): bool
    {
        $model = $this->findOneOrFail($id);

        return (bool) $model->delete();
    }

    /**
     * Find a record by primary key.
     *
     * @return null|TModel
     */
    public function findOne(mixed $pk): ?Model
    {
        $model = $this->query()->find($pk);

        if ($model === null) {
            return null;
        }

        if (! $model instanceof Model) {
            throw new RuntimeException('Unexpected query result.');
        }

        /** @var TModel $model */
        return $model;
    }

    /**
     * Find a record by primary key or fail.
     *
     * @return TModel
     */
    public function findOneOrFail(mixed $pk): Model
    {
        $model = $this->findOne($pk);

        if ($model === null) {
            throw new RecordNotFoundException("Record with ID {$pk} not found.");
        }

        return $model;
    }

    /**
     * Create a query builder for the model.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->modelClass::query();
    }

    /**
     * Run a database transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public function transaction(Closure $callback)
    {
        $connectionName = $this->connectionName();

        if ($connectionName !== null) {
            return Db::connection($connectionName)->transaction($callback);
        }

        return Db::transaction($callback);
    }

    /**
     * Batch insert records.
     *
     * Notice:
     * - This does not trigger model events.
     * - This does not apply fillable / guarded.
     * - This does not automatically handle timestamps.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insert(array $rows): bool
    {
        if ($rows === []) {
            return true;
        }

        $this->assertSameColumns($rows);

        return $this->query()->insert($rows);
    }

    /**
     * Batch insert records by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertChunks(array $rows, int $chunkSize = 500): bool
    {
        if ($rows === []) {
            return true;
        }

        $this->assertValidChunkSize($chunkSize);

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            if (! $this->insert($chunk)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Batch insert records with timestamps.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertWithTimestamps(array $rows): bool
    {
        return $this->insertChunksWithTimestamps($rows);
    }

    /**
     * Batch insert records with timestamps by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertChunksWithTimestamps(array $rows, int $chunkSize = 500): bool
    {
        if ($rows === []) {
            return true;
        }

        $rows = $this->applyTimestamps($rows, true, true);

        return $this->insertChunks($rows, $chunkSize);
    }

    /**
     * Create or update one record.
     *
     * Suitable for small amount of data.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values): Model
    {
        $model = $this->query()->updateOrCreate($attributes, $values);

        if (! $model instanceof Model) {
            throw new RuntimeException('Unexpected updateOrCreate result.');
        }

        /** @var TModel $model */
        return $model;
    }

    /**
     * Batch upsert records.
     *
     * Suitable when your Hyperf database builder supports upsert().
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     * @param array<int, string> $updateColumns
     */
    public function upsert(array $rows, array $uniqueBy, array $updateColumns): int
    {
        if ($rows === []) {
            return 0;
        }

        if ($uniqueBy === []) {
            throw new InvalidArgumentException('Unique by columns cannot be empty.');
        }

        if ($updateColumns === []) {
            throw new InvalidArgumentException('Update columns cannot be empty.');
        }

        $this->assertSameColumns($rows);

        return (int) $this->query()->upsert($rows, $uniqueBy, $updateColumns);
    }

    /**
     * MySQL batch insert on duplicate key update.
     *
     * This depends on PRIMARY KEY or UNIQUE KEY in MySQL.
     *
     * Example:
     * UNIQUE KEY uk_sheet_brief_hash (sheet_id, brief_hash)
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertOnDuplicateKeyUpdate(array $rows, array $updateColumns): int
    {
        if ($rows === []) {
            return 0;
        }

        if ($updateColumns === []) {
            throw new InvalidArgumentException('Update columns cannot be empty.');
        }

        $this->assertSameColumns($rows);

        $columns = array_keys($rows[0]);

        foreach ($updateColumns as $column) {
            if (! in_array($column, $columns, true)) {
                throw new InvalidArgumentException("Update column [{$column}] does not exist in insert rows.");
            }
        }

        $quotedTable = $this->quoteIdentifier($this->table());

        $quotedColumns = array_map(
            fn (string $column): string => $this->quoteIdentifier($column),
            $columns
        );

        $placeholders = [];
        $bindings = [];

        foreach ($rows as $row) {
            $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $updates = [];

        foreach ($updateColumns as $column) {
            $quotedColumn = $this->quoteIdentifier($column);

            /**
             * MySQL syntax:
             * column = VALUES(column)
             *
             * For MySQL 8.0.20+, VALUES() is deprecated but still commonly works.
             * If you need future-proof syntax, you can rewrite this SQL with alias:
             * INSERT INTO table (...) VALUES (...) AS new
             * ON DUPLICATE KEY UPDATE col = new.col
             */
            $updates[] = "{$quotedColumn} = VALUES({$quotedColumn})";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $quotedTable,
            implode(',', $quotedColumns),
            implode(',', $placeholders),
            implode(',', $updates)
        );

        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * MySQL batch insert on duplicate key update by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertChunksOnDuplicateKeyUpdate(
        array $rows,
        array $updateColumns,
        int $chunkSize = 500
    ): int {
        if ($rows === []) {
            return 0;
        }

        $this->assertValidChunkSize($chunkSize);

        $affected = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $affected += $this->insertOnDuplicateKeyUpdate($chunk, $updateColumns);
        }

        return $affected;
    }

    /**
     * MySQL batch insert on duplicate key update with timestamps by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertChunksOnDuplicateKeyUpdateWithTimestamps(
        array $rows,
        array $updateColumns,
        int $chunkSize = 500
    ): int {
        if ($rows === []) {
            return 0;
        }

        $rows = $this->applyTimestamps($rows, true, true);

        if (! in_array('updated_at', $updateColumns, true)) {
            $updateColumns[] = 'updated_at';
        }

        return $this->insertChunksOnDuplicateKeyUpdate(
            $rows,
            $updateColumns,
            $chunkSize
        );
    }

    /**
     * Create a new model instance.
     *
     * @return TModel
     */
    protected function newModel(): Model
    {
        return new $this->modelClass();
        /** @var TModel $model */
    }

    /**
     * Get model table name.
     */
    protected function table(): string
    {
        return $this->newModel()->getTable();
    }

    /**
     * Get model connection name.
     */
    protected function connectionName(): ?string
    {
        return $this->newModel()->getConnectionName();
    }

    /**
     * Get current datetime string.
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Escape MySQL identifier.
     *
     * Example:
     * users      => `users`
     * user_name  => `user_name`
     */
    protected function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            throw new InvalidArgumentException('Identifier cannot be empty.');
        }

        if (str_contains($identifier, '`')) {
            throw new InvalidArgumentException("Invalid identifier: {$identifier}");
        }

        return "`{$identifier}`";
    }

    /**
     * Execute affecting SQL statement by model connection.
     *
     * @param array<int, mixed> $bindings
     */
    protected function affectingStatement(string $sql, array $bindings = []): int
    {
        $connectionName = $this->connectionName();

        if ($connectionName !== null) {
            return Db::connection($connectionName)->affectingStatement($sql, $bindings);
        }

        return Db::affectingStatement($sql, $bindings);
    }

    /**
     * Apply created_at / updated_at to rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function applyTimestamps(
        array $rows,
        bool $withCreatedAt = true,
        bool $withUpdatedAt = true
    ): array {
        $now = $this->now();

        foreach ($rows as &$row) {
            if ($withCreatedAt) {
                $row['created_at'] ??= $now;
            }

            if ($withUpdatedAt) {
                $row['updated_at'] ??= $now;
            }
        }

        unset($row);

        return $rows;
    }

    /**
     * Make sure all rows have the same columns.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    protected function assertSameColumns(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);
        sort($columns);

        foreach ($rows as $index => $row) {
            $currentColumns = array_keys($row);
            sort($currentColumns);

            if ($columns !== $currentColumns) {
                throw new InvalidArgumentException("All rows must have the same columns. Invalid row index: {$index}.");
            }
        }
    }

    /**
     * @phpstan-assert positive-int $chunkSize
     */
    protected function assertValidChunkSize(int $chunkSize): void
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than 0.');
        }
    }
}
