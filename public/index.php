<?php

if ('OPTIONS' === $_SERVER['REQUEST_METHOD'] ??= '')
{
    $allowed        = ['POST', 'GET', 'OPTIONS', 'DELETE', 'PUT'];
    $allowedHeaders = ['Content-Type', 'Authorization', 'X-Api-Key'];
    header('Allow: ' . implode(', ', $allowed));
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowed));
    header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
    http_response_code(200);

    exit;
}

if ( ! defined('RUNTIME_VERSION'))
{
    require_once __DIR__ . '/../src/runtime.php';

    exit;
}

return function ()
{
    require_once __DIR__ . '/../src/router.php';
};
