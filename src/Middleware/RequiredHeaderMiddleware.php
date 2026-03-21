<?php

namespace Middleware;

use Reindeer\SymfonyMiddleware\Contracts\MiddlewareInterface;
use Reindeer\SymfonyMiddleware\Contracts\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class RequiredHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(private array $values, private int $code = 400) {}

    public static function make(array $rules, int $code = 400)
    {
        return new static($rules, $code);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $rules = [];

        foreach ($this->values as $name => $value)
        {
            if (empty($value))
            {
                continue;
            }

            if ( ! is_string($name))
            {
                $rules[$value] = true;
                continue;
            }
            $rules[$name] = $value;
        }
        $ok    = true;

        foreach ($rules as $name => $value)
        {
            if (true === $value && ! $request->headers->has($name))
            {
                $ok = false;
                break;
            }

            if ($value !== $request->headers->get($name))
            {
                $ok = false;
                break;
            }
        }

        if ( ! $ok)
        {
            return \JsonResponseView::newResponse()->setStatusCode($this->code)->setError(
                \CurlHandler::getReasonPhrase($this->code)
            )->toResponse();
        }

        return $handler->handle($request);
    }
}
