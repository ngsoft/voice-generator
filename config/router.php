<?php

// register routes/actions

use Controller\Action\HomeAction;
use Controller\Action\TranslateAction;
use Controller\OpenApiController;
use Controller\SynthesisController;
use Middleware\AccessControlMiddleware;
use Middleware\AuthorizationMiddleware;
use Middleware\RequiredHeaderMiddleware;
use Middleware\WebAccessControlMiddleware;
use NGSOFT\Routing\Middleware\CorsMiddleware;
use NGSOFT\Routing\Middleware\JsonHttpErrorMiddleware;
use NGSOFT\Routing\RouteGroup;
use NGSOFT\Routing\Router;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TemplateEngine\Renderer;

return function (Router $router)
{
    // api docs
    $router->group('/api', function (RouteGroup $router)
    {
        // OpenApi + Redocly
        $router->get('/doc', OpenApiController::class)
            ->setName('api_doc');
        $router->get('/doc.json', OpenApiController::class);
        $router->get('/doc.yaml', OpenApiController::class)
            ->setName('api_doc_download');
    })->add(WebAccessControlMiddleware::make('LOCAL_ACL', '^127.0,::1,^192.168', 'error/404'));

    // api group protected by api key and global ACL
    $router->group('/api', function (RouteGroup $router)
    {
        $router->get('/voices', [SynthesisController::class, 'getvoices'])
            ->setName('voices');
        $router->get('/voice/{provider}/{lang}/{name}', [SynthesisController::class, 'getvoice'])
            ->setName('voice');
        $router->post('/speak', [SynthesisController::class, 'speak'])
            ->setName('speak');
        $router->get('/providers', [SynthesisController::class, 'getproviders']);
    })->add(AccessControlMiddleware::make('GLOBAL_ACL', '*'))->add(RequiredHeaderMiddleware::make([
        'X-Api-Key' => env_get('API_KEY', '', false),
    ], 401))->add(AuthorizationMiddleware::class)->add(JsonHttpErrorMiddleware::class)->add(CorsMiddleware::class);

    // api group protected by global ACL only
    $router->group('/api', function (RouteGroup $router)
    {
        $router->get('/speak/download/{identifier}', [SynthesisController::class, 'download'])
            ->setName('download');
        $router->post('/translate', TranslateAction::class);
    })->add(AccessControlMiddleware::make('GLOBAL_ACL', '*'))->add(JsonHttpErrorMiddleware::class)->add(CorsMiddleware::class);

    // home page (page/player-form)
    $router->get('/', HomeAction::class)
        ->add(AuthorizationMiddleware::class)
        ->add(WebAccessControlMiddleware::make('WEB_ACL', '*'))
        ->setName('app_index');

    // fallback routes, to be added last
    $router->get('/api/{path:.*}', fn () => throw new NotFoundHttpException()); // JSON Error
    $router->get('/{path:.*}', fn (Renderer $renderer) => $renderer->render('error/404')); // HTML Error page
};
