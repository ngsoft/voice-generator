<?php

namespace Interfaces;

use View\OpenApiResponseView;

interface HasOpenApiDataModel extends \JsonSerializable
{
    /**
     * @return class-string<OpenApiResponseView>
     */
    public static function getOpenApiDataModel(): string;
}
