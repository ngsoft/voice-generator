<?php

namespace Action;

use Renderer\JsonResponse;

class NotAllowedAction extends \Action
{
    protected function execute(\Request $request): \Response
    {
        return JsonResponse::newBadMethod();
    }
}
