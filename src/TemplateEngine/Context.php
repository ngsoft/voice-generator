<?php

namespace TemplateEngine;

use Symfony\Component\HttpFoundation\HeaderBag;
use Traits\HasAttributes;

final class Context
{
    use HasAttributes;

    public readonly HeaderBag $headers;

    public function __construct()
    {
        $this->headers = new HeaderBag();
    }
}
