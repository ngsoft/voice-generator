<?php

// register routes/actions
use Controller\HelloController;
use Controller\OpenApiController;
use NGSOFT\Routing\Middleware\CorsMiddleware;
use NGSOFT\Routing\Middleware\JsonHttpErrorMiddleware;
use NGSOFT\Routing\RouteGroup;
use NGSOFT\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Action::addActions([
//    '/' => 'page/home',
//    '~/hello(/.*)?$~' => HelloController::class,
//    '/500' => 'error/500',
// ]);

return function (Router $router)
{
    $router->group('/api', function (RouteGroup $router)
    {
        $router->get('/hello', HelloController::class);
        $router->get('/hello/{name:.*}', [HelloController::class, 'hello'])
            ->setName('api_hello');

        // OpenApi + Redocly
        $router->get('/doc', OpenApiController::class);
        $router->get('/doc.json', OpenApiController::class);
        $router->get('/doc.yaml', OpenApiController::class)->setName('api_doc_download');

        // fallback route, to be added last
        $router->get('/{path:.*}', fn () => throw new NotFoundHttpException());
    })->add(JsonHttpErrorMiddleware::class)->add(CorsMiddleware::class);

    $router->get('/', fn (Request $request) => load_action($request, 'page/home'))
        ->setName('app_index');

    $router->get('/hello', HelloController::class);
    $router->get('/hello/{name:.*}', [HelloController::class, 'hello'])
        ->setName('hello');
    $router->get('/500', fn (Request $request) => load_action($request, 'error/500'));

    // fallback route, to be added last
    $router->get('/{path:.*}', fn (Request $request) => load_action($request, 'error/404'));
};
