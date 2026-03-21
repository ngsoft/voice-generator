<?php

// register routes/actions

use Controller\OpenApiController;
use Controller\SynthesisController;
use Middleware\AccessControlMiddleware;
use Middleware\RequiredHeaderMiddleware;
use NGSOFT\Routing\Middleware\CorsMiddleware;
use NGSOFT\Routing\Middleware\JsonHttpErrorMiddleware;
use NGSOFT\Routing\RouteGroup;
use NGSOFT\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return function (Router $router)
{
    $router->group('/api', function (RouteGroup $router)
    {
        // voice list

        $acl     = AccessControlMiddleware::make('GLOBAL_ACL', '*');
        $local   = AccessControlMiddleware::make('LOCAL_ACL', '^127.0,::1,^192.168');
        $api_key = RequiredHeaderMiddleware::make([
            'X-Api-Key' => env_get('API_KEY', '', false),
        ], 401);

        $router->get('/voices', [SynthesisController::class, 'getvoices'])->add($api_key)->add($acl)
            ->setName('voices');
        $router->get('/voice/{provider}/{name}', [SynthesisController::class, 'getvoice'])->add($api_key)->add($acl)
            ->setName('voice');
        $router->post('/speak', [SynthesisController::class, 'speak'])->add($api_key)->add($acl)
            ->setName('speak');
        $router->get('/speak/download/{identifier}', [SynthesisController::class, 'download'])->add($acl)
            ->setName('download');

        // OpenApi + Redocly
        $router->get('/doc', OpenApiController::class)->add($local);
        $router->get('/doc.json', OpenApiController::class)->add($local);
        $router->get('/doc.yaml', OpenApiController::class)->add($local)
            ->setName('api_doc_download');

        // fallback route, to be added last
        $router->get('/{path:.*}', fn () => throw new NotFoundHttpException());
    })->add(JsonHttpErrorMiddleware::class)->add(CorsMiddleware::class);

    $router->get('/', fn (Request $request) => load_action($request, 'page/home'))
        ->setName('app_index');

    $router->get('/500', fn (Request $request) => load_action($request, 'error/500'));

    // fallback route, to be added last
    $router->get('/{path:.*}', fn (Request $request) => load_action($request, 'error/404'));
};
