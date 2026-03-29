<?php

namespace Controller;

use Interfaces\ActionInterface;
use NGSOFT\Container\Container;
use NGSOFT\Routing\Interface\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Service\LoggerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Service\Attribute\Required;
use TemplateEngine\Renderer;
use Traits\ErrorLoggerTrait;

abstract class BaseController
{
    use ErrorLoggerTrait;

    #[Required]
    protected Container $container;

    public function __invoke(Request $request): Response
    {
        if ($this instanceof ActionInterface)
        {
            return $this->execute($request)->toResponse();
        }
        throw new NotFoundHttpException();
    }

    final protected function decodeJsonBody(Request $request, ?array $defaultValue = null): ?array
    {
        if ($content = $this->jsonBody($request))
        {
            $value = json_decode($content, true);
            return is_array($value) ? $value : $defaultValue;
        }

        return $defaultValue;
    }

    final protected function jsonBody(Request $request): ?string
    {
        $content = $request->getContent();

        if ( ! json_validate($content))
        {
            return null;
        }
        return $content;
    }

    /**
     * Loads service from Container.
     *
     * @template T
     *
     * @param class-string<T>|string $id
     * @param mixed                  ...$params
     *
     * @return mixed|T
     */
    final protected function get(string $id, mixed ...$params)
    {
        return $params ? $this->container->make($id, $params) : $this->container->get($id);
    }

    final protected function getLogger(): LoggerInterface
    {
        return $this->container->get(LoggerService::class);
    }

    final protected function getJsonResponse(): \JsonResponseView
    {
        return $this->get(\JsonResponseView::class);
    }

    final protected function toErrorResponse(int $code = 500): \JsonResponseView
    {
        return \JsonResponseView::newResponse()->setStatusCode($code)->setError(
            \CurlHandler::getReasonPhrase($code)
        );
    }

    /**
     * Generates a URL from the given parameters.
     */
    final protected function generatePath(string $route, array $parameters = [], array $query = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->get(UrlGeneratorInterface::class)->generate($route, $parameters, $query, $referenceType);
    }

    /**
     * Generates a full URL from the given parameters.
     */
    final protected function generateUrl(string $route, array $parameters = [], array $query = []): string
    {
        return $this->get(UrlGeneratorInterface::class)->generate($route, $parameters, $query, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param int $status The HTTP status code (302 "Found" by default)
     */
    final protected function redirect(string $url, int $status = 302): \ResponseView
    {
        return \ResponseView::of(new RedirectResponse($url, $status));
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @param int $status The HTTP status code (302 "Found" by default)
     */
    final protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): \ResponseView
    {
        return $this->redirect($this->generateUrl($route, $parameters), $status);
    }

    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     *
     * @param int $status The HTTP status code (200 "OK" by default)
     */
    final protected function json(mixed $data, int $status = 200, array $headers = []): \ResponseView
    {
        if (null === $data)
        {
            return \ResponseView::of(new JsonResponse('null', $status, $headers, true));
        }

        return \ResponseView::of(new JsonResponse($data, $status, $headers, is_string($data)));
    }

    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     */
    final protected function file(\SplFileInfo|string $file, ?string $fileName = null, string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): \ResponseView
    {
        if ($file instanceof \SplFileInfo)
        {
            $file = $file->getFilename();
        }
        return (new \FileResponseView())->setFile($file)
            ->setFileName($fileName ?? $file)
            ->setDisposition($disposition);
    }

    /**
     * Renders a view.
     */
    final protected function render(string $view, array $parameters = []): \ResponseView
    {
        $response = new \ResponseView();
        return $response
            ->setContent($this->renderView($view, $parameters))
            ->setStatusCode($this->getRenderer()->getAttribute('status_code', 200))
            ->setHeaders(array_replace($response->getAllHeaders(), $this->getRenderer()->getContext()->headers->all()));
    }

    /**
     * Returns a rendered view.
     */
    final protected function renderView(string $view, array $parameters = []): string
    {
        return $this->getRenderer()->renderView($view, $parameters);
    }

    private function getRenderer(): Renderer
    {
        return $this->get(Renderer::class);
    }
}
