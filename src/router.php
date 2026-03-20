<?php

use Middleware\ExceptionMiddleware;
use Symfony\Component\HttpFoundation\JsonResponse;

// load tailwind theme
vite('app/app.css');
$logger  = Services::getLogger();
$request = Services::getRequest();
$router  = Services::getRouter();

if ($router)
{
    $router->prepend(ExceptionMiddleware::class);

    $response = $router->handle($request);
    $router->emit($response);

    $path     = $request->getPathInfo();

    // log response
    if ($response instanceof JsonResponse)
    {
        $log = str_format('RESPONSE %s%s[%s][code:%d]%s', [
            $request->getMethod(),
            "[path={$path}]",
            formated_execution_time(),
            $response->getStatusCode(),
            env_get('APP_DEBUG') ? $response->getContent() : '',
        ]);
    } else
    {
        $log = str_format('RESPONSE %s%s[%s][code:%d]', [
            $request->getMethod(),
            "[path={$path}]",
            formated_execution_time(),
            $response->getStatusCode(),
        ]);
    }
    $logger::setBackTrace(false);
    $logger->log($log);

    exit;
}

load_action($request, $request->attributes->get(
    'action',
    $request->query->get('action', $request->request->get(
        'action',
        $request->attributes->get('path', '')
    ))
), true);
