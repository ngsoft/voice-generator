<?php

use Action\DefaultAction;
use Action\NotAllowedAction;
use FastRoute\RouteCollector;

return function (RouteCollector $router)
{
    // catch all
    $router->addRoute(['POST', 'PUT', 'DELETE', 'PATCH'], '/{path:.*}', NotAllowedAction::class);
    $router->addRoute(['GET', 'HEAD'], '/{path:.*}', DefaultAction::class);
};
