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
use Traits\ErrorLoggerTrait;

readonly class MicrosoftEdgeVoiceProvider implements SpeechSynthesisInterface
{
    use ErrorLoggerTrait;

    private string $storage;

    public function __construct(private EdgeTTS $client, private CacheInterface $cache)
    {
        $this->storage = resolve_path('%data%/edge_voice');
    }

    public function getName(): string
    {
        return 'edge';
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

            $this->client->synthesize($utterance->getText(), $utterance->getVoice(), $options);
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
                $list = $this->client->getVoices();
                ! empty($list) && $this->cache->set($key, $list, 600);
            } catch (\Throwable $exception)
            {
                $this->logError($exception);
                return [];
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
                    'friendlyName' => trim(explode(' - ', var_get('FriendlyName', $voice, $shortName))[0]),
                    'voiceUri'     => sprintf('%s://%s', $this->getName(), $shortName),
                ]);
            }
        }

        return $result;
    }

    public function getFile(string $identifier): ?\FileResponseView
    {
        $files = [$identifier];

        if ( ! preg_match('#\.\w+$#', $identifier))
        {
            $files = ["{$identifier}.mp3", "{$identifier}.wav", "{$identifier}.ogg"];
        }

        foreach ($files as $file)
        {
            $path = resolve_path($this->storage, $file);

            if (is_file($path))
            {
                return \FileResponseView::newResponse()->setFile($path);
            }
        }
        return null;
    }

    public function hasVoice(string $name): bool
    {
        $voices = $this->getVoices();
        return ! empty($voices) && array_any($voices, fn (SpeechSynthesisVoice $voice) => $voice->getName() === $name);
    }
}
