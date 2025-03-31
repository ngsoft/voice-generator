<?php

use NGSOFT\Container\ContainerInterface;
use View\TemplateView;

return function (ContainerInterface $container)
{
    $container->setMany([
        'routes'                 => fn () => require __DIR__ . '/router.php',
        TemplateView::class      => fn () => new TemplateView(),
        ApplicationLogger::class => ApplicationLogger::getLogger(),
    ]);
};
