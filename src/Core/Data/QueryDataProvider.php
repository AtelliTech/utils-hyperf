<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils\Core\Data;

use Hyperf\Database\Query\Builder;
use InvalidArgumentException;
use stdClass;

class QueryDataProvider
{
    protected Builder $query;

    protected int $page;

    protected int $pageSize;

    protected ?string $sort;

    protected int $totalCount = 0;

    /**
     * @var array<int, stdClass>
     */
    protected array $rows = [];

    protected bool $prepared = false;

    /**
     * @var null|callable(stdClass): mixed
     */
    protected $rowProcessor;

    /**
     * @param array{
     *     page?: int,
     *     pageSize?: int,
     *     sort?: string
     * } $params
     * @param null|callable(stdClass): mixed $rowProcessor
     */
    public function __construct(
        Builder $query,
        array $params = [],
        ?callable $rowProcessor = null
    ) {
        $this->query = clone $query;
        $this->rowProcessor = $rowProcessor;

        $this->page = max((int) ($params['page'] ?? 1), 1);
        $this->pageSize = max((int) ($params['pageSize'] ?? 20), 1);
        $this->sort = $params['sort'] ?? null;
    }

    /**
     * @return array<int, stdClass>
     */
    public function getRows(): array
    {
        if (! $this->prepared) {
            $this->prepareData();
        }

        return $this->rows;
    }

    /**
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
     * @return array{_data: array<int, mixed>, _meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        $rows = $this->getRows();

        $data = $this->rowProcessor !== null
            ? array_map($this->rowProcessor, $rows)
            : array_map(fn (stdClass $row) => (array) $row, $rows);

        return [
            '_data' => $data,
            '_meta' => $this->getMeta(),
        ];
    }

    protected function prepareData(): void
    {
        $query = clone $this->query;
        $this->applySort($query);

        $this->totalCount = $query->count();

        if ($this->totalCount === 0) {
            $this->rows = [];
            $this->prepared = true;
            return;
        }

        $offset = ($this->page - 1) * $this->pageSize;

        $this->rows = $query
            ->skip($offset)
            ->take($this->pageSize)
            ->get()
            ->toArray();

        $this->prepared = true;
    }

    protected function applySort(Builder $query): void
    {
        if (! $this->sort) {
            return;
        }

        foreach (explode(',', $this->sort) as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-+');

            if ($column === '') {
                throw new InvalidArgumentException('Invalid sort parameter.');
            }

            $query->orderBy($column, $direction);
        }
    }
}
