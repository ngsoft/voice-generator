<?php

namespace Action;

use Renderer\JsonResponse;

class DefaultAction extends \Action
{
    protected function execute(\Request $request): \Response
    {
        return JsonResponse::newNotFound()
            ->setError('resource not found');
    }
}
