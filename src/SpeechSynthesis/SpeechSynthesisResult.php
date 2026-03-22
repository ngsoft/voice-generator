<?php

namespace SpeechSynthesis;

use Service\AudioConverter;

readonly class SpeechSynthesisResult
{
    public function __construct(
        public string $provider,
        public string $voice,
        public string $identifier,
        public string $path,
        public string $content_type,
        public float $duration,
    ) {}

    public function getSize(): int
    {
        return (int) @filesize($this->path);
    }

    public function pullContent(): string
    {
        try
        {
            return $this->getContent();
        } finally
        {
            @unlink($this->path);
        }
    }

    public function getContent(): string
    {
        return @file_get_contents($this->path) ?: '';
    }

    public function toFileResponseView(): \FileResponseView
    {
        return \FileResponseView::newResponse()->setFileName($this->path)
            ->addHeader('X-Media-Duration', number_format($this->duration, 6, '.', ''))
            ->addHeader('X-Media-Time', $this->getHumanReadableDuration());
    }

    public function toBase64(): ?string
    {
        if ($content = $this->getContent())
        {
            return base64_encode($content);
        }
        return null;
    }

    public function toBase64Data(): ?string
    {
        if ($base = $this->toBase64())
        {
            return sprintf('data:%s;base64,%s', $this->content_type, $base);
        }
        return null;
    }

    public function getHumanReadableDuration(): string
    {
        return AudioConverter::secToTimeMicro($this->duration);
    }
}
