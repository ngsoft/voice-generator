<?php

declare(strict_types=1);

namespace Model;

use OpenApi\Attributes as OA;
use Sql\ValidationError;

#[OA\Schema]
class SearchModel extends DataModel
{
    #[OA\Property(description: 'Search query', type: 'string', nullable: true)]
    protected ?string $search        = null;
    #[OA\Property(description: 'Start date', type: 'string', example: '2026-01', nullable: true)]
    protected ?\DateTime $start_date = null;
    #[OA\Property(description: 'End date', type: 'string', example: '2026-12', nullable: true)]
    protected ?\DateTime $end_date   = null;

    #[OA\Property(description: 'Page number', nullable: true, minimum: 1)]
    protected int $page              = 1;

    #[OA\Property(description: 'Items per page', nullable: true, minimum: 1)]
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
