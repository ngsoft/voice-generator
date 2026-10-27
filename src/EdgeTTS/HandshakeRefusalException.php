<?php

declare(strict_types=1);

namespace EdgeTTS;

use Afaya\EdgeTTS\Exception\HandshakeException;

/**
 * Handshake refusal with optional HTTP response body (vendor HandshakeException has none).
 */
final class HandshakeRefusalException extends HandshakeException
{
    public function __construct(
        int $statusCode,
        array $headers = [],
        private readonly string $body = '',
    ) {
        parent::__construct($statusCode, $headers);
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
