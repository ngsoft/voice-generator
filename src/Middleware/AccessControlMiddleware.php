<?php

namespace Middleware;

use Reindeer\SymfonyMiddleware\Contracts\MiddlewareInterface;
use Reindeer\SymfonyMiddleware\Contracts\RequestHandlerInterface;
use Service\AccessService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class AccessControlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $rules,
        private AccessService $accessService,
    ) {}

    public static function make(string $env, $default = '')
    {
        return new static(env_get($env, $default, false), \Services::make(AccessService::class));
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ( ! $this->accessService->doCheckAcl($request, $this->rules))
        {
            return \JsonResponseView::newForbidden()->toResponse();
        }
        return $handler->handle($request);
    }
}
