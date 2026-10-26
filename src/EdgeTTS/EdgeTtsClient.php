<?php

declare(strict_types=1);

namespace EdgeTTS;

use Afaya\EdgeTTS\Service\EdgeTTS;
use Afaya\EdgeTTS\Service\Protocol;
use Afaya\EdgeTTS\Transport\WebSocket;
use Service\LoggerService;

/**
 * EdgeTTS client that replaces the library's Sec-MS-GEC v1 token with v2
 * (required by Microsoft since ~2026-10).
 */
final class EdgeTtsClient extends EdgeTTS
{
    /** Matches Chromium major used by afaya/edge-tts 1.9.1, with v2 prefix. */
    public const SEC_MS_GEC_VERSION = '2-155.0.4283.45';

    public function __construct(
        array $options = [],
        private readonly ?LoggerService $logger = null,
    ) {
        $options['secMsGecVersion'] ??= self::SEC_MS_GEC_VERSION;
        parent::__construct($options);
    }

    protected function connect(string $url, array $headers, float $timeout): WebSocket
    {
        return parent::connect($this->withV2Token($url, 'wss'), $headers, $timeout);
    }

    protected function httpGet(string $url, array $headers): array
    {
        return parent::httpGet($this->withV2Token($url, 'voices'), $headers);
    }

    private function withV2Token(string $url, string $channel): string
    {
        $parts                       = parse_url($url);

        if (false === $parts || ! isset($parts['scheme'], $parts['host']))
        {
            $this->logger?->warning('Sec-MS-GEC v2: could not parse URL for %s channel', [$channel]);

            return $url;
        }

        $unix                        = Protocol::now();
        parse_str($parts['query'] ?? '', $query);
        $token                       = SecMsGecV2::generate($unix);
        $query['Sec-MS-GEC']         = $token;
        $query['Sec-MS-GEC-Version'] = self::SEC_MS_GEC_VERSION;

        $authority                   = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $target                      = $authority . ($parts['path'] ?? '/');

        $this->logger?->info(
            'Sec-MS-GEC v2 %s %s version=%s unix=%d token=%s',
            [$channel, $target, self::SEC_MS_GEC_VERSION, $unix, $this->tokenPreview($token)]
        );
        $this->logger?->debug('Sec-MS-GEC v2 full token=%s', [$token]);

        $pairs                       = [];

        foreach ($query as $name => $value)
        {
            $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        }

        return $parts['scheme'] . '://' . $target . '?' . implode('&', $pairs);
    }

    private function tokenPreview(string $token): string
    {
        // v2: "<16 hex CS64>-<64 hex digest>" — log CS64 + digest prefix only
        $dash = strpos($token, '-');

        if (false === $dash)
        {
            return substr($token, 0, 16) . '…';
        }

        return substr($token, 0, $dash + 1 + 8) . '…';
    }
}
