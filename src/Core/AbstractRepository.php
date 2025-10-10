<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core;

use Closure;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Db;
use Hyperf\DbConnection\Model\Model;
use InvalidArgumentException;

/**
 * @template TModel of Model
 * @implements RepositoryInterface<TModel>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        /** @var TModel $model */
        $model = new $this->modelClass();
        $model->fill($data);
        $model->save();
        return $model;
    }

    /**
     * Update a record by primary key.
     *
     * @param array<string, mixed> $data
     * @return bool True on success, false on failure
     */
    public function update(mixed $id, array $data): bool
    {
        $model = $this->findOne($id);
        return $model->update($data);
    }

    /**
     * Delete a record by primary key.
     *
     * @return bool True on success, false on failure
     */
    public function delete(mixed $id): bool
    {
        $model = $this->findOne($id);
        return $model->delete();
    }

    /**
     * Find a record by primary key.
     *
     * @return TModel
     */
    public function findOne(mixed $pk): Model
    {
        /** @var Builder<TModel> $query */
        $query = $this->modelClass::query();

        /** @var null|TModel $model */
        $model = $query->find($pk);

        if ($model === null) {
            throw new InvalidArgumentException("Record({$pk}) of {$this->modelClass} not found");
        }

        return $model;
    }

    /**
     * Create a query builder for the model.
     *
     * @return Builder<TModel>
     */
    public function find(): Builder
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
        return Db::transaction($callback);
    }
}
