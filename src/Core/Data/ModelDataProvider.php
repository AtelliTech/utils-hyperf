<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Data;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use InvalidArgumentException;

/**
 * A data provider for Eloquent models, supporting pagination, sorting and relations.
 *
 * @template TModel of \Hyperf\Database\Model\Model
 */
class ModelDataProvider
{
    /**
     * @var Builder<TModel>
     */
    protected Builder $query;

    protected int $page;

    protected int $pageSize;

    protected ?string $sort;

    /**
     * @var array<string>
     */
    protected array $with = [];

    protected int $totalCount = 0;

    /**
     * @var array<int, TModel>
     */
    protected array $models = [];

    protected bool $prepared = false;

    /**
     * @var null|callable
     */
    protected $modelProcessor;

    /**
     * @param Builder<TModel> $query
     * @param array{
     *     page?: int,
     *     pageSize?: int,
     *     sort?: string,
     *     with?: array<string>
     * } $params
     * @param null|callable(TModel): mixed $modelProcessor
     */
    public function __construct(
        Builder $query,
        array $params = [],
        ?callable $modelProcessor = null
    ) {
        $this->query = clone $query;
        $this->modelProcessor = $modelProcessor;

        $this->page = max((int) ($params['page'] ?? 1), 1);
        $this->pageSize = max((int) ($params['pageSize'] ?? 20), 1);
        $this->sort = $params['sort'] ?? null;
        $this->with = $params['with'] ?? [];

        // preload relations
        if (! empty($this->with)) {
            $this->query->with($this->with);
        }
    }

    /**
     * Get models.
     *
     * @return array<int, TModel>
     */
    public function getModels(): array
    {
        if (! $this->prepared) {
            $this->prepareData();
        }

        return $this->models;
    }

    /**
     * Get pagination meta data.
     *
     * @return array{page: int, pageSize: int, totalCount: int, pageCount: int}
     */
    public function getMeta(): array
    {
        if (! $this->prepared) {
            $this->prepareData();
        }

        $pageCount = (int) ceil($this->totalCount / $this->pageSize);

        return [
            'page' => $this->page,
            'pageSize' => $this->pageSize,
            'totalCount' => $this->totalCount,
            'pageCount' => max($pageCount, 1),
        ];
    }

    /**
     * To array.
     *
     * @return array{_data: array<int, array<string, mixed>>, _meta: array{page: int, pageSize: int, totalCount: int, pageCount: int}}
     */
    public function toArray(): array
    {
        $models = $this->getModels();

        if ($this->modelProcessor !== null) {
            $data = array_map($this->modelProcessor, $models);
        } else {
            $data = array_map(fn ($model) => $model->toArray(), $models);
        }

        return [
            '_data' => $data,
            '_meta' => $this->getMeta(),
        ];
    }

    /**
     * Execute the query and prepare the data.
     */
    protected function prepareData(): void
    {
        $query = clone $this->query;
        $this->applySort($query);

        // Optimize: get total count first, then query data
        $this->totalCount = $query->count();

        // If total count is 0, return empty array
        if ($this->totalCount === 0) {
            $this->models = [];
            $this->prepared = true;
            return;
        }

        $offset = ($this->page - 1) * $this->pageSize;

        /** @var Collection<int, TModel> $collection */
        $collection = $query
            ->skip($offset)
            ->take($this->pageSize)
            ->get();

        $this->models = $collection->all();
        $this->prepared = true;
    }

    /**
     * Apply sorting to the query.
     *
     * @param Builder<TModel> $query
     */
    protected function applySort(Builder $query): void
    {
        if (! $this->sort) {
            return;
        }

        $sorts = explode(',', $this->sort);
        foreach ($sorts as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-+');

            if (empty($column)) {
                throw new InvalidArgumentException('Invalid sort parameter: empty column name.');
            }

            $query->orderBy($column, $direction);
        }
    }
}
