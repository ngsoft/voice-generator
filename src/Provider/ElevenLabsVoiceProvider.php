<?php

namespace Provider;

use Enum\AudioFormat;
use HttpClient\CurlResponse;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Service\AudioConverter;
use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisInterface;
use SpeechSynthesis\SpeechSynthesisResult;
use SpeechSynthesis\SpeechSynthesisUtterance;
use SpeechSynthesis\SpeechSynthesisVoice;
use Traits\SynthesisProviderTrait;

readonly class ElevenLabsVoiceProvider implements SpeechSynthesisInterface
{
    use SynthesisProviderTrait;

    private string $storage;

    private string $base_path;

    public function __construct(private string $api_key, private CacheInterface $cache)
    {
        $this->base_path = 'https://api.elevenlabs.io';
        $this->storage   = resolve_path('%data%/eleven_voice');

        if (empty($this->api_key))
        {
            throw new \InvalidArgumentException('ElevenLabs API Key is required.');
        }
    }

    public function getName(): string
    {
        return 'elevenlabs';
    }

    public function getDescription(): string
    {
        return 'ElevenLabs: Free AI Voice Generator & Voice Agents Platform';
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
            $content_type = AudioFormat::MP3->mime();
            $dest         = resolve_path($this->storage, $uuid);
            $file         = AudioFormat::MP3->addExtension($dest);

            @mkdir(dirname($dest), 0777, true);

            $resp         = $this->doSpeak($utterance);

            if ($content = $resp->getContents())
            {
                if (@file_put_contents($file, $content))
                {
                    $duration = AudioConverter::getMediaDuration($file);

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
                }
            }

            throw SpeechSynthesisException::make('Cannot generate file');
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

    /**
     * @param null|string $lang
     *
     * @return SpeechSynthesisVoice[]
     */
    public function getVoices(?string $lang = null): array
    {
        static $key = 'eleven-voice-list';
        $list       = $this->cache->get($key);

        if ( ! $list)
        {
            try
            {
                $list = $this->doGetVoices();
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
            $id                 = var_get('voice_id', $voice);
            $verified_languages = var_get('verified_languages', $voice, []);

            foreach ($verified_languages as $language)
            {
                if ($locale = var_get('locale', $language))
                {
                    if ( ! $lang || str_contains(strtolower($locale), strtolower($lang)) || $id === $lang)
                    {
                        $result[] = SpeechSynthesisVoice::make([
                            'lang'         => $locale,
                            'name'         => "{$id}",
                            'friendlyName' => trim(explode(' - ', var_get('name', $voice, $id))[0]),
                            'voiceUri'     => sprintf('%s://%s', $this->getName(), $id),
                        ])->addMeta('model_id', var_get('model_id', $language));
                    }
                }
            }
        }

        return $result;
    }

    private function query_string(array $input = []): string
    {
        return '?' . http_build_query(array_replace([
            'optimize_streaming_latency' => 0,
            'output_format'              => 'mp3_44100_128',
        ], $input));
    }

    private function headers(array $input = []): array
    {
        return array_replace($input, [
            'xi-api-key' => $this->api_key,
        ]);
    }

    private function doSpeak(SpeechSynthesisUtterance $utterance): CurlResponse
    {
        $selected = null;

        foreach ($this->getVoices($utterance->getVoice()) as $voice)
        {
            if ($utterance->getLang() === $voice->getLang())
            {
                $selected = $voice;
                break;
            }
        }

        if ( ! $selected)
        {
            throw SpeechSynthesisException::make('Voice %s %s not found', $utterance->getLang(), $utterance->getVoice());
        }

        $params   = [
            'text'           => $utterance->getText(),
            'voice_settings' => [
                'stability'        => 0.5,
                'similarity_boost' => 0.75,
                'speed'            => min(max(0.7, $utterance->getRate()), 1.2),
            ],
        ];

        $id       = $utterance->getVoice();

        if ($model_id = $selected->getMeta('model_id'))
        {
            $params += [
                'model_id'      => $model_id,
                'language_code' => preg_split('#[-_]+#', $utterance->getLang())[0],
            ];
        }

        $resp     = \CurlHandler::makeHttpRequest(
            $this->base_path . '/v1/text-to-speech/' . $id . $this->query_string(),
            json_encode($params),
            'POST',
            $this->headers([
                'Accept'       => AudioFormat::MP3->mime(),
                'Content-Type' => 'application/json',
            ])
        );

        if (200 !== $resp->status)
        {
            if (is_array($data = $resp->getDecodedContents()))
            {
                $detail = var_get('detail', $data, []);

                if ($message = var_get('message', $detail))
                {
                    throw SpeechSynthesisException::make('Cannot fetch speech: %s', $message);
                }
            }
            throw SpeechSynthesisException::make('Cannot fetch speech');
        }

        return $resp;
    }

    private function doGetVoices(): array
    {
        $resp = \CurlHandler::makeHttpRequest(
            $this->base_path . '/v1/voices',
            headers: $this->headers(['Accept' => 'application/json'])
        );

        if (200 === $resp->status && is_array($data = $resp->getDecodedContents()))
        {
            if ($voices = var_get('voices', $data))
            {
                return $voices;
            }
        }

        return [];
    }
}
