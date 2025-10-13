<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Data;

use Hyperf\Database\Model\Builder;
use InvalidArgumentException;

/**
 * A data provider for Eloquent models, supporting pagination and sorting.
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

    protected int $totalCount = 0;

    /**
     * @var array<int, mixed>
     */
    protected array $models = [];

    protected bool $prepared = false;

    /**
     * @param Builder<TModel> $query
     * @param array<string,mixed> $params
     */
    public function __construct(Builder $query, array $params = [])
    {
        $this->query = clone $query; // avoid modify original query

        // load params
        $this->page = max((int) ($params['page'] ?? 1), 1);
        $this->pageSize = max((int) ($params['pageSize'] ?? 20), 1);
        $this->sort = $params['sort'] ?? null;
    }

    /**
     * Get models.
     *
     * @return array<int, mixed>
     */
    public function getModels(): array
    {
        if (! $this->prepared) {
            $this->prepareData();
        }

        return $this->models ?? [];
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
            'pageCount' => $pageCount,
        ];
    }

    /**
     * To array.
     *
     * @return array{data: array<int, mixed>, meta: array{page: int, pageSize: int, totalCount: int, pageCount: int}}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->getModels(),
            'meta' => $this->getMeta(),
        ];
    }

    /**
     * Execute the query and prepare the data.
     */
    protected function prepareData(): void
    {
        $query = clone $this->query;
        $this->applySort($query);
        $this->totalCount = $query->count();

        $offset = ($this->page - 1) * $this->pageSize;

        $this->models = $query
            ->skip($offset)
            ->take($this->pageSize)
            ->get()
            ->toArray();

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

        // 支援多個欄位: "sort=-id,name"
        $sorts = explode(',', $this->sort);
        foreach ($sorts as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-+');
            if (! $column) {
                throw new InvalidArgumentException('Invalid sort parameter.');
            }
            $query->orderBy($column, $direction);
        }
    }
}
