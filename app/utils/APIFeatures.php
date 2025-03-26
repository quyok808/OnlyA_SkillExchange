<?php

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;

class APIFeatures
{
    protected $query;
    protected $queryString;

    public function __construct(Builder $query, array $queryString)
    {
        $this->query = $query;
        $this->queryString = $queryString;
    }

    public function filter()
    {
        // Implement filtering logic
        return $this;
    }

    public function sort()
    {
        if (isset($this->queryString['sort'])) {
            $this->query->orderBy($this->queryString['sort']);
        }
        return $this;
    }

    public function paginate()
    {
        $limit = $this->queryString['limit'] ?? 10;
        $page = $this->queryString['page'] ?? 1;
        $this->query->limit($limit)->offset(($page - 1) * $limit);
        return $this;
    }

    public function getQuery()
    {
        return $this->query;
    }
}
