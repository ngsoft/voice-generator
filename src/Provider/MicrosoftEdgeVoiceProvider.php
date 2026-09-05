<?php

namespace Provider;

use Afaya\EdgeTTS\Service\EdgeTTS;
use Enum\AudioFormat;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Service\AudioConverter;
use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisInterface;
use SpeechSynthesis\SpeechSynthesisResult;
use SpeechSynthesis\SpeechSynthesisUtterance;
use SpeechSynthesis\SpeechSynthesisVoice;
use Traits\SynthesisProviderTrait;

readonly class MicrosoftEdgeVoiceProvider implements SpeechSynthesisInterface
{
    use SynthesisProviderTrait;

    private const DEFAULT_SOCKET_TIMEOUT = 60;

    private string $storage;

    /** Explicit `SPEECH_SYNTHESIS_SOCKET_TIMEOUT` override, `null` when left unset. */
    private ?int $socketTimeout;

    public function __construct(private EdgeTTS $client, private CacheInterface $cache)
    {
        $this->storage       = resolve_path('%data%/edge_voice');
        $timeout             = env_get('SPEECH_SYNTHESIS_SOCKET_TIMEOUT');
        $this->socketTimeout = is_numeric($timeout) ? max(1, (int) $timeout) : null;
    }

    public function getName(): string
    {
        return 'edge';
    }

    public function getDescription(): string
    {
        return 'Microsoft Edge Read Aloud Natural Voices';
    }

    public function speak(SpeechSynthesisUtterance $utterance): SpeechSynthesisResult
    {
        if ($utterance->isError())
        {
            throw SpeechSynthesisException::make('Invalid SpeechSynthesisUtterance');
        }

        $mask = @umask(0);
        @ob_start();

        try
        {
            $uuid         = Uuid::uuid4()->toString();
            $content_type = 'audio/mpeg';
            $dest         = resolve_path($this->storage, $uuid);
            $file         = "{$dest}.mp3";
            @mkdir(dirname($dest), 0777, true);

            $options      = [
                'pitch'  => '+0Hz',
                'rate'   => '0%',
                'volume' => '0%',
            ];

            if ($utterance->getPitch() > 1)
            {
                $options['pitch'] = sprintf('+%dHz', floor(100 * ($utterance->getPitch() - 1)));
            } elseif ($utterance->getPitch() < 1)
            {
                $options['pitch'] = sprintf('-%dHz', floor(100 * (1 - $utterance->getPitch())));
            }

            if ($utterance->getRate() > 1)
            {
                $options['rate'] = sprintf('+%d', 10 * $utterance->getRate()) . '%';
            } elseif ($utterance->getRate() < 1)
            {
                $options['rate'] = sprintf('-%d', floor(100 * (1 - $utterance->getRate()))) . '%';
            }

            if ($utterance->getVolume() < 1)
            {
                $options['volume'] = sprintf('-%d', floor(100 * (1 - $utterance->getVolume()))) . '%';
            } elseif ($utterance->getVolume() > 1)
            {
                $options['volume'] = sprintf('+%d', floor(100 * ($utterance->getVolume() - 1))) . '%';
            }

            // EdgeTTS → React Socket → react/dns probes Windows DNS via `wmic`.
            // WMIC was removed in Windows 11 24H2+; shell_exec then prints to STDERR.
            // A PATH stub silences that; empty nameservers fall back to 8.8.8.8.
            $this->withUsableSocketTimeout(
                fn () => $this->withSilencedMissingWmic(
                    fn () => $this->client->synthesize($utterance->getText(), $utterance->getVoice(), $options)
                )
            );
            $this->client->toFile($dest);

            $duration     = AudioConverter::getMediaDuration($file);

            if ($format = $utterance->getFormat())
            {
                if (AudioFormat::PCM === $format)
                {
                    if ( ! AudioConverter::convert($file, $format->addExtension($dest), true))
                    {
                        @unlink($file);
                        throw SpeechSynthesisException::make('PCM Conversion error');
                    }
                }

                if (AudioFormat::OGG === $format)
                {
                    if ( ! AudioConverter::convertOgg($file, $format->addExtension($dest), true))
                    {
                        @unlink($file);
                        throw SpeechSynthesisException::make('OGG Conversion error');
                    }
                }
                $file         = $format->addExtension($dest);
                $content_type = $format->mime();
            }

            return new SpeechSynthesisResult(
                $this->getName(),
                $utterance->getVoice(),
                $uuid,
                $file,
                $content_type,
                $duration
            );
        } catch (\Throwable $error)
        {
            $this->logError($error);
            throw SpeechSynthesisException::make('An error occured during speech synthesis');
        } finally
        {
            @umask($mask);
            @ob_end_clean();
        }
    }

    public function getVoices(?string $lang = null): array
    {
        static $key = 'edge-voice-list';

        $list       = $this->cache->get($key);

        if ( ! $list)
        {
            try
            {
                set_default_error_handler();
                $list = $this->client->getVoices();
                ! empty($list) && $this->cache->set($key, $list, 600);
            } catch (\Throwable $exception)
            {
                $this->logError($exception);
                return [];
            } finally
            {
                restore_error_handler();
            }
        }

        if ($lang)
        {
            $lang = trim(str_replace('_', '-', $lang));
        }

        $result     = [];

        foreach ($list as $voice)
        {
            $locale    = var_get('Locale', $voice);
            $shortName = var_get('ShortName', $voice);

            if ( ! $lang || str_contains(strtolower($locale), strtolower($lang)) || $shortName === $lang)
            {
                $result[] = SpeechSynthesisVoice::make([
                    'lang'         => var_get('Locale', $voice),
                    'name'         => var_get('ShortName', $voice),
                    'friendlyName' => trim(var_get('DisplayName', $voice)),
                    'voiceUri'     => sprintf('%s://%s', $this->getName(), $shortName),
                    'provider'     => $this->getName(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Force a positive `default_socket_timeout` around the synthesis.
     * react/socket derives its connect timeout from that ini setting; PHP reads `-1`
     * as "no limit" for native streams, but react takes it literally and cancels the
     * TLS handshake at once ("timed out after -1 seconds"), leaving no audio at all.
     * The WebSdk php.ini ships `-1` from PHP 8.3 onwards, hence the guard.
     *
     * `SPEECH_SYNTHESIS_SOCKET_TIMEOUT` overrides the ini value for every synthesis;
     * left unset, a usable ini value is kept as-is and only a broken one is repaired.
     *
     * @see https://github.com/reactphp/socket/blob/1.x/src/Connector.php
     */
    private function withUsableSocketTimeout(callable $callback): void
    {
        $previous = ini_get('default_socket_timeout');

        if (null === $this->socketTimeout && is_numeric($previous) && $previous > 0)
        {
            $callback();

            return;
        }

        ini_set('default_socket_timeout', (string) ($this->socketTimeout ?? self::DEFAULT_SOCKET_TIMEOUT));

        try
        {
            $callback();
        } finally
        {
            false !== $previous && ini_set('default_socket_timeout', $previous);
        }
    }

    /**
     * Prepend a no-op `wmic.cmd` to PATH when WMIC is missing (Windows 11 24H2+).
     * react/dns still calls `wmic` for nameserver discovery; without a stub, cmd.exe
     * prints "'wmic' n'est pas reconnu..." to STDERR on every synthesize().
     *
     * @see https://github.com/reactphp/dns/issues/228
     */
    private function withSilencedMissingWmic(callable $callback): void
    {
        if ('\\' !== DIRECTORY_SEPARATOR || $this->isWmicAvailable())
        {
            $callback();

            return;
        }

        $stubDir      = resolve_path('%data%/bin');
        @mkdir($stubDir, 0777, true);
        $stub         = $stubDir . DIRECTORY_SEPARATOR . 'wmic.cmd';

        if ( ! is_file($stub))
        {
            file_put_contents($stub, "@echo off\r\nrem Stub for WMIC removed from Windows 11 24H2+\r\nexit /b 0\r\n");
        }

        $previousPath = getenv('PATH') ?: '';
        putenv('PATH=' . $stubDir . PATH_SEPARATOR . $previousPath);
        $_ENV['PATH'] = $stubDir . PATH_SEPARATOR . $previousPath;

        try
        {
            $callback();
        } finally
        {
            putenv('PATH=' . $previousPath);
            $_ENV['PATH'] = $previousPath;
        }
    }

    private function isWmicAvailable(): bool
    {
        static $available = null;

        if (null !== $available)
        {
            return $available;
        }

        $where            = @shell_exec('where wmic 2>nul');

        return $available = is_string($where) && str_contains($where, 'wmic');
    }
}
