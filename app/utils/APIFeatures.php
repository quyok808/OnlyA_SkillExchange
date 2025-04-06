<?php

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class APIFeatures
{
    protected Builder $query;
    protected array $queryString;
    protected int $page;
    protected int $limit;
    protected ?int $total = null; // Thêm biến để lưu tổng số bản ghi

    public function __construct(Builder $query, array $queryString)
    {
        $this->query = $query;
        $this->queryString = $queryString;
        $this->page = 1;
        $this->limit = 10;
    }

    public function filter(): static
    {
        $queryData = $this->queryString;
        $excludedFields = ['page', 'limit', 'sort', 'fields', 'skillName'];
        $filterData = array_diff_key($queryData, array_flip($excludedFields));
        $modelColumns = Schema::getColumnListing($this->query->getModel()->getTable());

        foreach ($filterData as $field => $value) {
            if (isset($value) && $value !== '' && in_array($field, $modelColumns)) {
                $this->query->whereRaw('LOWER(' . $field . ') LIKE ?', ['%' . strtolower($value) . '%']);
            }
        }

        return $this;
    }

    public function sort(): static
    {
        if (isset($this->queryString['sort']) && !empty($this->queryString['sort'])) {
            $sortFields = explode(',', $this->queryString['sort']);
            $modelColumns = Schema::getColumnListing($this->query->getModel()->getTable());

            foreach ($sortFields as $sortField) {
                $sortField = trim($sortField);
                $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
                $sortField = ltrim($sortField, '-');

                if (in_array($sortField, $modelColumns)) {
                    $this->query->orderBy($sortField, $direction);
                }
            }
        } else {
            $this->query->orderBy('created_at', 'desc');
        }

        return $this;
    }

    public function paginate(): static
    {
        // 1. Calculate page and limit from query string or defaults
        $this->page = max(1, (int)($this->queryString['page'] ?? 1));
        // Use a consistent default, maybe 10 or 20?
        $defaultLimit = 10;
        $this->limit = max(1, (int)($this->queryString['limit'] ?? $defaultLimit));

        // 2. Calculate the number of records to skip (offset)
        $skip = ($this->page - 1) * $this->limit;

        // 3. Apply the limit and offset to the Eloquent query builder <<< THIS WAS MISSING
        $this->query->offset($skip)->limit($this->limit);

        return $this; // Return instance for chaining
    }

    // Thêm phương thức count()
    public function count(): static
    {
        $this->total = $this->query->toBase()->getCountForPagination(); // Tối ưu cho phân trang
        return $this;
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getTotalPages(): int
    {
        if ($this->total === null) {
            $this->count(); // Tự động đếm nếu chưa có
        }
        return max(1, (int)ceil($this->total / $this->limit));
    }
}
