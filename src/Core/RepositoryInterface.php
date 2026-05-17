<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core;

use Closure;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Model\Model;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * Update a record by primary key.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function update(mixed $id, array $data): Model;

    /**
     * Update a record and return changed fields.
     *
     * @param array<string, mixed> $data
     * @return array{
     *     model: TModel,
     *     dirty: array<string, mixed>,
     *     changes: array<string, array{old: mixed, new: mixed}>
     * }
     */
    public function updateWithChanges(mixed $id, array $data): array;

    /**
     * Delete a record by primary key.
     */
    public function delete(mixed $id): bool;

    /**
     * Find a record by primary key.
     *
     * @return null|TModel
     */
    public function findOne(mixed $pk): ?Model;

    /**
     * Find a record by primary key or fail.
     *
     * @return TModel
     */
    public function findOneOrFail(mixed $pk): Model;

    /**
     * Create a query builder for the model.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder;

    /**
     * Run a database transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public function transaction(Closure $callback);

    /**
     * Batch insert records.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insert(array $rows): bool;

    /**
     * Batch insert records by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertChunks(array $rows, int $chunkSize = 500): bool;

    /**
     * Batch insert records with timestamps.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertWithTimestamps(array $rows): bool;

    /**
     * Batch insert records with timestamps by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertChunksWithTimestamps(array $rows, int $chunkSize = 500): bool;

    /**
     * Create or update one record.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     * @return TModel
     */
    public function updateOrCreate(array $attributes, array $values): Model;

    /**
     * Batch upsert.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $uniqueBy
     * @param array<int, string> $updateColumns
     */
    public function upsert(array $rows, array $uniqueBy, array $updateColumns): int;

    /**
     * MySQL insert on duplicate key update.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertOnDuplicateKeyUpdate(array $rows, array $updateColumns): int;

    /**
     * MySQL insert on duplicate key update by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertChunksOnDuplicateKeyUpdate(
        array $rows,
        array $updateColumns,
        int $chunkSize = 500
    ): int;

    /**
     * MySQL insert on duplicate key update with timestamps by chunks.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $updateColumns
     */
    public function insertChunksOnDuplicateKeyUpdateWithTimestamps(
        array $rows,
        array $updateColumns,
        int $chunkSize = 500
    ): int;
}
