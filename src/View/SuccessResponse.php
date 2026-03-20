<?php

namespace View;

use OpenApi\Attributes as OA;

/**
 * This is the default response for the API.
 */
#[OA\Schema]
class SuccessResponse extends OpenApiResponseView
{
    #[OA\Property(description: 'Response status', nullable: false)]
    public bool $success    = true;

    #[OA\Property(description: 'Status/Error message', type: 'string', nullable: true)]
    public ?string $message = null;

    public function setMessage(string $message): static
    {
        return $this->setAttribute('message', $message);
    }
}
