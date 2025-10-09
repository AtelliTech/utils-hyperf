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
     */
    public function update(mixed $id, array $data): bool;

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
     * Create a query builder for the model.
     *
     * @return Builder<TModel>
     */
    public function find(): Builder;

    /**
     * Run a database transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    public function transaction(Closure $callback);
}
