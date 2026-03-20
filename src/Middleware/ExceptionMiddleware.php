<?php

namespace Middleware;

use NGSOFT\Routing\Interface\HighPriorityMiddlewareInterface;
use NGSOFT\Routing\Middleware\CorsMiddleware;
use Reindeer\SymfonyMiddleware\Contracts\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use View\ErrorResponse;

/**
 * Transform normal Exceptions as HTTP Error Exceptions.
 */
class ExceptionMiddleware implements HighPriorityMiddlewareInterface, RequestHandlerInterface
{
    private ?Response $response = null;

    public function __construct(private readonly CorsMiddleware $corsMiddleware) {}

    /**
     * Request handler used to get response from CorsMiddleware
     * (MiddlewareInterface is detected before RequestHandlerInterface on RequestRunner)).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        return $this->response ?? new Response();
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try
        {
            return $handler->handle($request);
        } catch (\Throwable $exception)
        {
            log_exception($exception);
            $code = $exception instanceof \InvalidArgumentException ? 400 : 500;

            if ($exception instanceof HttpExceptionInterface)
            {
                $code = $exception->getStatusCode();
            }

            if (str_starts_with($request->getPathInfo(), '/api'))
            {
                $this->response
                    = $exception instanceof HttpExceptionInterface
                    ? ErrorResponse::make()->setMessage(
                        \CurlHandler::getReasonPhrase($exception->getStatusCode())
                    )->toResponseView($exception->getStatusCode())
                        ->setHeaders($exception->getHeaders())
                        ->toResponse()
                    : \JsonResponseView::newInternalError(
                        env_get('APP_DEBUG', false)
                            ? $exception->getMessage()
                            : \CurlHandler::getReasonPhrase($code)
                    )->setStatusCode($code)->toResponse();

                return $this->corsMiddleware->process($request, $this);
            }

            return \Services::getPage()->setView(
                404 === $code ? 'error/404' : 'error/500'
            )->getResponse(env_get('APP_DEBUG', false) ? [
                'code'   => $code,
                'reason' => \CurlHandler::getReasonPhrase($code),
            ] : [])->toResponse();
        }
    }
}
