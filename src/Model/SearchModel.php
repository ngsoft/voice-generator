<?php

declare(strict_types=1);

namespace Model;

use Sql\ValidationError;

class SearchModel extends DataModel
{
    protected ?string $search        = null;
    protected ?\DateTime $start_date = null;
    protected ?\DateTime $end_date   = null;

    protected int $page              = 1;

    protected int $limit             = 10;

    public function paginate(array $list)
    {
        return array_slice($list, $this->limit * ($this->page - 1), $this->limit);
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    protected function validateData(array $data)
    {
        $this->search = isset($data['search'])
            ? trim((string) $data['search'])
            : null;
        $data['limit'] ??= $this->limit;

        if ( ! is_numeric($data['limit']))
        {
            throw ValidationError::make('limit must be a number');
        }
        $this->limit  = max(1, (int) $data['limit']);

        $data['page']  ??= $this->page;

        if ( ! is_numeric($data['page']))
        {
            throw ValidationError::make('page must be a number');
        }
        $this->page   = max(1, (int) $data['page']);

        if ( ! empty($data['start_date'] ?? $data['startDate'] ?? ''))
        {
            $this->start_date = $this->parseDate($data['start_date'] ?? $data['startDate']);
        }

        if ( ! empty($data['end_date'] ?? $data['endDate'] ?? ''))
        {
            $this->end_date = $this->parseDate($data['end_date'] ?? $data['endDate']);
        }
        return $this;
    }
}
