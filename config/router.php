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
    // api docs
    $router->group('/api', function (RouteGroup $router)
    {
        // OpenApi + Redocly
        $router->get('/doc', OpenApiController::class);
        $router->get('/doc.json', OpenApiController::class);
        $router->get('/doc.yaml', OpenApiController::class)
            ->setName('api_doc_download');
    })->add(AccessControlMiddleware::make('LOCAL_ACL', '^127.0,::1,^192.168'));

    // api group
    $router->group('/api', function (RouteGroup $router)
    {
        $api_key = RequiredHeaderMiddleware::make([
            'X-Api-Key' => env_get('API_KEY', '', false),
        ], 401);

        $router->get('/voices', [SynthesisController::class, 'getvoices'])->add($api_key)
            ->setName('voices');
        $router->get('/voice/{provider}/{lang}/{name}', [SynthesisController::class, 'getvoice'])->add($api_key)
            ->setName('voice');
        $router->post('/speak', [SynthesisController::class, 'speak'])->add($api_key)
            ->setName('speak');
        $router->get('/providers', [SynthesisController::class, 'getproviders'])->add($api_key);
        $router->get('/speak/download/{identifier}', [SynthesisController::class, 'download'])
            ->setName('download');
    })->add(AccessControlMiddleware::make('GLOBAL_ACL', '*'))
        ->add(JsonHttpErrorMiddleware::class)->add(CorsMiddleware::class);

    $router->get('/', fn (Request $request) => load_action($request, 'page/home'))
        ->setName('app_index');

    $router->get('/500', fn (Request $request) => load_action($request, 'error/500'));

    // fallback routes, to be added last
    $router->get('/api/{path:.*}', fn () => throw new NotFoundHttpException());
    $router->get('/{path:.*}', fn (Request $request) => load_action($request, 'error/404'));
};
