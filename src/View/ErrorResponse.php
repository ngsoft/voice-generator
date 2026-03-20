<?php

namespace View;

use OpenApi\Attributes as OA;

/**
 * This is the default error response for the API.
 */
#[OA\Schema]
class ErrorResponse extends SuccessResponse
{
    #[OA\Property(description: 'Response status', example: false, nullable: false)]
    public bool $success = false;
}
