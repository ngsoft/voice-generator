<?php

use Action\DefaultAction;
use Action\ListenAction;
use Action\NotAllowedAction;
use Controller\VoiceController;
use FastRoute\RouteCollector;

return function (RouteCollector $router)
{
    $router->get('/voice/list', [VoiceController::class, 'listVoices']);
    $router->addRoute(['GET', 'POST'], '/voice/speak/{path:.*}/{id}', [VoiceController::class, 'playVoice']);


    $router->addRoute(['GET'], '/', ListenAction::class);
    // catch all
    $router->addRoute(['POST', 'PUT', 'DELETE', 'PATCH'], '/{path:.*}', NotAllowedAction::class);
    $router->addRoute(['GET'], '/{path:.*}', DefaultAction::class);
};
