<?php

declare(strict_types=1);

namespace EdgeTTS;

use Afaya\EdgeTTS\Config\Constants;
use Afaya\EdgeTTS\Exception\EdgeTTSException;
use Afaya\EdgeTTS\Service\EdgeTTS;
use Afaya\EdgeTTS\Service\Protocol;
use Afaya\EdgeTTS\Transport\Http;
use Afaya\EdgeTTS\Transport\WebSocket;
use Service\LoggerService;

/**
 * EdgeTTS client that can replace the library's Sec-MS-GEC v1 token with v2
 * (required by Microsoft since ~2026-10). Toggle via SPEECH_SYNTHESIS_SEC_MS_GEC.
 */
final class EdgeTtsClient extends EdgeTTS
{
    /** Matches Chromium major used by afaya/edge-tts 1.9.1, with v2 prefix. */
    public const SEC_MS_GEC_VERSION = '2-155.0.4283.45';

    private const WEBSOCKET_GUID    = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /** Cap logged / stored refusal bodies (Microsoft error pages can be large). */
    private const BODY_LOG_LIMIT    = 4096;

    private readonly bool $useV2;

    public function __construct(
        array $options = [],
        private readonly ?LoggerService $logger = null,
    ) {
        $this->useV2 = self::resolveUseV2($options['secMsGec'] ?? null);
        unset($options['secMsGec']);

        if ($this->useV2)
        {
            $options['secMsGecVersion'] ??= self::SEC_MS_GEC_VERSION;
        } else
        {
            $options['secMsGecVersion'] ??= Constants::SEC_MS_GEC_VERSION;
        }

        parent::__construct($options);

        $this->logger?->info(
            'Sec-MS-GEC mode=%s version=%s',
            [$this->useV2 ? 'v2' : 'v1', $options['secMsGecVersion']]
        );
    }

    protected function connect(string $url, array $headers, float $timeout): WebSocket
    {
        return $this->connectCapturingRefusalBody($this->applySecMsGec($url, 'wss'), $headers, $timeout);
    }

    protected function httpGet(string $url, array $headers): array
    {
        return parent::httpGet($this->applySecMsGec($url, 'voices'), $headers);
    }

    /**
     * Same as vendor WebSocket::connect, but reads the HTTP body when the upgrade is refused
     * so callers can log Microsoft's error payload.
     *
     * @param array<string, string> $headers
     *
     * @throws HandshakeRefusalException when the server answers anything but 101
     */
    private function connectCapturingRefusalBody(string $url, array $headers, float $timeout): WebSocket
    {
        $target  = Http::target($url, ['ws', 'wss']);
        $stream  = Http::open($target['secure'], $target['host'], $target['port'], $timeout);
        $socket  = new WebSocket($stream, null, $timeout);

        $key     = base64_encode(random_bytes(16));
        $request = "GET {$target['target']} HTTP/1.1\r\n"
            . "Host: {$target['hostHeader']}\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n";

        foreach ($headers as $name => $value)
        {
            $request .= "{$name}: {$value}\r\n";
        }

        try
        {
            Http::writeAll($stream, $request . "\r\n");
            [$status, $responseHeaders] = Http::readHead($stream, $target['host'], $timeout);
        } catch (EdgeTTSException $e)
        {
            $socket->close();
            throw $e;
        }

        if (101 !== $status)
        {
            $body = $this->tryReadResponseBody($stream, $responseHeaders, $timeout);
            $socket->close();
            throw new HandshakeRefusalException($status, $responseHeaders, $body);
        }

        $accept  = $responseHeaders['sec-websocket-accept'] ?? '';

        if ($accept !== base64_encode(sha1($key . self::WEBSOCKET_GUID, true)))
        {
            $socket->close();
            throw new EdgeTTSException('Invalid Sec-WebSocket-Accept in the handshake response');
        }

        return $socket;
    }

    /**
     * @param resource              $stream
     * @param array<string, string> $headers
     */
    private function tryReadResponseBody($stream, array $headers, float $timeout): string
    {
        try
        {
            if (isset($headers['content-length']))
            {
                $length = (int) $headers['content-length'];

                if ($length <= 0)
                {
                    return '';
                }

                $read   = min($length, self::BODY_LOG_LIMIT);
                $body   = Http::readExactly($stream, $read, $timeout);

                return $length > self::BODY_LOG_LIMIT ? $body . '…' : $body;
            }

            if (false !== stripos($headers['transfer-encoding'] ?? '', 'chunked'))
            {
                $body = '';

                while (strlen($body) < self::BODY_LOG_LIMIT)
                {
                    $line = fgets($stream);

                    if (false === $line)
                    {
                        break;
                    }
                    $size = hexdec(trim(explode(';', $line, 2)[0]));

                    if (0 === $size)
                    {
                        break;
                    }
                    $need = min($size, self::BODY_LOG_LIMIT - strlen($body));
                    $body .= Http::readExactly($stream, $need, $timeout);

                    if ($need < $size)
                    {
                        return $body . '…';
                    }
                    fgets($stream);
                }

                return $body;
            }

            // Connection: close / no length — best-effort, short read.
            $chunk = @fread($stream, self::BODY_LOG_LIMIT);

            return is_string($chunk) ? $chunk : '';
        } catch (\Throwable)
        {
            return '';
        }
    }

    private function applySecMsGec(string $url, string $channel): string
    {
        if ( ! $this->useV2)
        {
            $this->logger?->debug('Sec-MS-GEC v1 (vendor) passthrough %s', [$channel]);

            return $url;
        }

        return $this->withV2Token($url, $channel);
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

    /**
     * @param mixed $override constructor option `secMsGec` (1|2|v1|v2) when set
     */
    private static function resolveUseV2(mixed $override = null): bool
    {
        $raw        = null !== $override && '' !== $override
            ? (string) $override
            : (string) env_get('SPEECH_SYNTHESIS_SEC_MS_GEC', '2', false);

        $normalized = strtolower(ltrim(trim($raw), 'vV'));

        return match ($normalized)
        {
            '1'     => false,
            '2'     => true,
            default => true,
        };
    }
}
