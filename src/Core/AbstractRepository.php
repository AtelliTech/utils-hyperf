<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core;

use Closure;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;
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
     * @param array<string, mixed> $data
     */
    public function update(mixed $id, array $data): bool
    {
        $model = $this->findOne($id);
        return $model->update($data);
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(mixed $id): bool
    {
        $model = $this->findOne($id);
        return $model ? $model->delete() : false;
    }

    /**
     * @return TModel
     * @throws InvalidArgumentException if record not found
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
