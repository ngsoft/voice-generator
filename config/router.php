<?php

use Action\DefaultAction;
use Action\NotAllowedAction;
use Controller\VoiceController;
use FastRoute\RouteCollector;

return function (RouteCollector $router)
{
    $router->get('/voice/list', [VoiceController::class, 'listVoices']);
    $router->get('/voice/speak/{path:.*}/{id}', [VoiceController::class, 'playVoice']);
    // catch all
    $router->addRoute(['POST', 'PUT', 'DELETE', 'PATCH'], '/{path:.*}', NotAllowedAction::class);
    $router->addRoute(['GET', 'HEAD'], '/{path:.*}', DefaultAction::class);
};
