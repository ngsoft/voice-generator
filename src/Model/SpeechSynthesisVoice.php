<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Model;

class SpeechSynthesisVoice extends \BaseModel implements \JsonSerializable, \Stringable
{
    protected bool $default      = false;
    protected string $lang       = '';
    protected bool $localService = false;
    protected string $name       = '';
    protected string $voiceUri   = '';

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return static[]
     */
    public static function fromOnnxJson(string $file): array
    {
        $result   = [];

        $contents = @file_get_contents($file);

        if ($contents && $json = @json_decode($contents, true))
        {
            if (1 === $json['num_speakers'])
            {
                $json['speaker_id_map'] = [0];
            }

            foreach ($json['speaker_id_map'] as $id)
            {
                $result[] = self::make([
                    'lang'     => $json['language']['code'],
                    'name'     => sprintf(
                        'Piper %s [%s]#%d - %s (%s)',
                        ucfirst($json['dataset']),
                        $json['audio']['quality'],
                        $id,
                        $json['language']['name_native'],
                        $json['language']['country_english'],
                    ),
                    'voiceUri' => str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        substr(
                            $file,
                            strlen(\Globals::resolvePath(\Env::getItem('VOICE_REPOSITORY', ''))),
                            -10
                        ) . "/{$id}"
                    ),
                ]);
            }
        }

        return $result;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): static
    {
        $this->default = $default;
        return $this;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setLang(string $lang): static
    {
        $this->lang = $lang;
        return $this;
    }

    public function isLocalService(): bool
    {
        return $this->localService;
    }

    public function setLocalService(bool $localService): static
    {
        $this->localService = $localService;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getVoiceUri(): string
    {
        return $this->voiceUri;
    }

    public function setVoiceUri(string $voiceUri): static
    {
        $this->voiceUri = $voiceUri;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'default'      => $this->default,
            'lang'         => $this->lang,
            'localService' => $this->localService,
            'name'         => $this->name,
            'voiceURI'     => $this->voiceUri,
        ];
    }
}
