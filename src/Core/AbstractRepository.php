<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core;

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
    /** @var class-string<TModel> */
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
    public function create(array $data, bool $useTransaction = false): Model
    {
        $callback = function () use ($data): Model {
            /** @var TModel $model */
            $model = new $this->modelClass();
            $model->fill($data);
            $model->save();
            return $model;
        };

        return $useTransaction ? Db::transaction($callback) : $callback();
    }

    /**
     * Update a record by primary key.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function update(mixed $id, array $data): Model
    {
        $model = $this->findOne($id);
        if ($model === null) {
            throw new InvalidArgumentException("Record with ID {$id} not found.");
        }

        $model->fill($data);
        if ($model->isDirty() && ! $model->save()) {
            throw new RuntimeException("Failed to update record with ID {$id}.");
        }

        return $model->refresh();
    }

    /**
     * Delete a record by primary key.
     *
     * @return bool True on success, false on failure
     */
    public function delete(mixed $id): bool
    {
        $model = $this->findOne($id);
        if ($model === null) {
            throw new InvalidArgumentException("Record with ID {$id} not found.");
        }
        return $model->delete();
    }

    /**
     * Find a record by primary key.
     *
     * @return null|TModel
     */
    public function findOne(mixed $pk): ?Model
    {
        /**
         * @var null|TModel
         */
        return $this->modelClass::query()->find($pk);
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
