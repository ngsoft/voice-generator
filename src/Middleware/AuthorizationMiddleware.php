<?php

namespace Middleware;

use Reindeer\SymfonyMiddleware\Contracts\MiddlewareInterface;
use Reindeer\SymfonyMiddleware\Contracts\RequestHandlerInterface;
use Service\StringEncoderService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Traits\ErrorLoggerTrait;

/**
 * Creates an authorization cookie to use as bearer token from ts app
 * overriding X-Api-Key behavior.
 */
readonly class AuthorizationMiddleware implements MiddlewareInterface
{
    use ErrorLoggerTrait;

    public function __construct(private string $passphrase, private string $secret) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($this->passphrase)
        {
            $request->attributes->set('_session_authorization', StringEncoderService::encrypt($this->secret, $this->passphrase));

            // same origin & Authorization header
            $origin = $request->headers->get('Origin');

            if ($origin && str_starts_with($request->getSchemeAndHttpHost(), $origin) && $request->headers->has('Authorization'))
            {
                $authorization = $request->headers->get('Authorization');

                if (str_starts_with(strtolower($authorization), 'bearer '))
                {
                    $token = trim(substr($authorization, 7));

                    try
                    {
                        if ($decoded = StringEncoderService::decrypt($token, $this->passphrase))
                        {
                            $request->headers->set('X-Api-Key', $decoded);
                        }
                    } catch (\Throwable $error)
                    {
                        $this->logError($error);
                    }
                }
            }
        }

        $response = $handler->handle($request);
        $key      = env_get('APP_ID') . '_session_authorization';

        // set readable cookie header in html pages (not in stateless api)
        if ($this->passphrase
            && str_contains($response->headers->get('Content-Type', ''), 'text/html')
            && 200 === $response->getStatusCode())
        {
            if ( ! $request->cookies->has($key))
            {
                $response->headers->setCookie(new Cookie(
                    $key,
                    $request->attributes->get('_session_authorization'),
                    secure: true,
                    httpOnly: false,
                ));
            }
        }

        return $response;
    }
}
