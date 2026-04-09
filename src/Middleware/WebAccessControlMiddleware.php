<?php

namespace Middleware;

use Reindeer\SymfonyMiddleware\Contracts\MiddlewareInterface;
use Reindeer\SymfonyMiddleware\Contracts\RequestHandlerInterface;
use Service\AccessService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TemplateEngine\Renderer;

readonly class WebAccessControlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $rules,
        private string $page,
        private AccessService $accessService,
        private Renderer $renderer,
    ) {}

    public static function make(string $env, $default = '', $page = 'error/500')
    {
        return new static(
            env_get($env, $default, false),
            $page,
            \Services::make(AccessService::class),
            \Services::make(Renderer::class)
        );
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ( ! $this->accessService->doCheckAcl($request, $this->rules))
        {
            return $this->renderer->render(
                $this->page,
                ['code' => 403, 'reason' => \CurlHandler::getReasonPhrase(403)]
            );
        }
        return $handler->handle($request);
    }
}
