<?php

namespace View;

use OpenApi\Attributes as OA;

#[OA\Schema]
class PaginatedResponse extends SuccessResponse
{
    #[OA\Property(description: 'Total results', nullable: false)]
    public int $total = 0;
    #[OA\Property(description: 'Current page', nullable: false, minimum: 1)]
    public int $page  = 1;
    #[OA\Property(description: 'Results per page', nullable: false, minimum: 1)]
    public int $limit = 10;
}
