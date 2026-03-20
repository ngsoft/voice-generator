<?php

namespace Service;

use League\MimeTypeDetection\FinfoMimeTypeDetector;

readonly class MimeService
{
    private FinfoMimeTypeDetector $mime;

    private string $defaultValue;

    public function __construct()
    {
        $this->defaultValue = 'application/octet-stream';
        $this->mime         = new FinfoMimeTypeDetector();
    }

    public function fromFileName(string $file): string
    {
        return $this->mime->detectMimeTypeFromPath($file) ?? $this->defaultValue;
    }

    public function fromContent(string $content): string
    {
        return $this->mime->detectMimeTypeFromBuffer($content) ?? $this->defaultValue;
    }
}
