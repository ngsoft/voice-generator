<?php

namespace Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use View\OpenApiResponseView;

trait OpenApiHelperTrait
{
    /**
     * @return class-string<OpenApiResponseView>
     */
    abstract public static function getOpenApiDataModel(): string;

    /**
     * @return array<string,mixed>
     */
    abstract public function jsonSerialize();

    public function toView(): OpenApiResponseView
    {
        $class = static::getOpenApiDataModel();

        if (class_exists($class) && is_subclass_of($class, OpenApiResponseView::class))
        {
            return $class::make($this->jsonSerialize());
        }
        return OpenApiResponseView::make($this->jsonSerialize());
    }

    public function toResponseView(int $status = 200): \ResponseView
    {
        return \ResponseView::of($this->toResponse($status));
    }

    public function toResponse(int $status = 200): JsonResponse
    {
        return new JsonResponse(
            $this->jsonSerialize(),
            $status
        );
    }
}
