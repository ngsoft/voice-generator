<?php

namespace Action;

use Renderer\JsonResponse;

class ErrorAction extends \Action
{
    protected function execute(\Request $request): \Response
    {
        if (true === \Env::getItem('APP_DEBUG'))
        {
            return JsonResponse::newInternalError()
                ->setMessage($request->getAttribute(\RequestError::class)->getMessage());
        }

        return JsonResponse::newInternalError();
    }
}
