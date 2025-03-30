<?php

use Action\ClosureAction;
use FastRoute\Dispatcher;
use NGSOFT\Facades\Container;
use Renderer\JsonResponse;

use function FastRoute\simpleDispatcher;

$routeMatch  = substr($_SERVER['REQUEST_URI'], strlen(getBasePath()));

$routeMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$basepath    = getBasePath();
$dispatcher  = simpleDispatcher(Container::get('routes'));
$routeInfo   = $dispatcher->dispatch($routeMethod, $routeMatch);
$status      = $routeInfo[0];

$headers     = [];

foreach (getallheaders() as $name => $value)
{
    $real           = ucfirst(preg_replace_callback('#-[a-z]#', fn ($x) => strtoupper($x[0]), strtolower($name)));
    $headers[$real] = $value;
}

$json        = @file_get_contents('php://input') ?: '';

if (json_validate($json))
{
    $json = json_encode(json_decode($json, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else
{
    $json = null;
}

// trace request
ApplicationLogger::getLogger()
    ->log(
        'REQUEST: %s %s, PARAMS: %s',
        [
            $routeMethod,
            $routeMatch,
            $json ?: json_encode('GET' === $routeMethod ? $_GET : $_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]
    )
    ->log('REQUEST HEADERS: %s', [
        json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

switch ($status)
{
    case Dispatcher::METHOD_NOT_ALLOWED:
        JsonResponse::newBadMethod()->render();
        // no break
    case Dispatcher::FOUND:
        @list(, $handler, $args) = $routeInfo;

        $args   ??= [];

        $request                 = $_REQUEST;

        if (str_contains($headers['Content-Type'] ??= '', '/json'))
        {
            $request = json_decode($json, true) ?? [];
        }

        $ok                      = false;

        if (is_array($handler) && 2 === count($handler) && is_string($handler[1]))
        {
            list($class, $method) = $handler;

            if (is_object($class))
            {
                $handler = $class;
            } elseif (is_string($class) && class_exists($class))
            {
                $handler = Container::make($class);
            }
        } elseif (is_string($handler))
        {
            @list($handler, $method) = explode('@', $handler);

            if (class_exists($handler))
            {
                $handler = Container::make($handler);
            }
        } elseif ($handler instanceof Closure)
        {
            $handler = new ClosureAction($handler);
        }
        $method ??= '__invoke';
        $ok                      = is_object($handler) && method_exists($handler, $method);

        if ($ok)
        {
            $args = array_replace($args, $request);

            Container::set(Request::class, $request = new Request(
                $routeMatch,
                $routeMethod,
                $headers,
                array_replace($args, $request)
            ));

            try
            {
                Container::call([$handler, $method], [$request])
                    ->render();
            } catch (RequestError $error)
            {
                if ($action = $error->getAction())
                {
                    $request = $error->getRequest();
                    $action($error->getRequest())->render();
                }
                throw $error;
            }

            break;
        }

        // no break
    default:
        JsonResponse::newNotFound()->render();
}
