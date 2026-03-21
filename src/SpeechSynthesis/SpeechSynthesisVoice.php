<?php

namespace SpeechSynthesis;

use OpenApi\Attributes as OA;
use View\OpenApiResponseView;

#[OA\Schema]
class SpeechSynthesisVoice extends OpenApiResponseView
{
    #[OA\Property(
        description: 'A boolean value indicating whether the voice is the default voice for the current language.',
        nullable: false
    )]
    protected bool $default         = false;
    #[OA\Property(
        description: 'Returns a BCP 47 language tag indicating the language of the voice.',
        nullable: false
    )]
    protected string $lang          = 'en-US';
    #[OA\Property(
        description: 'A boolean value indicating whether the voice is supplied by a local speech synthesizer service.',
        nullable: false
    )]
    protected bool $localService    = false;
    #[OA\Property(
        description: 'Returns a human-readable name that represents the voice.',
        nullable: false
    )]
    protected string $name          = '';

    #[OA\Property(
        description: 'Returns a human-readable friendly name that represents the voice.',
        nullable: true
    )]
    protected string $friendlyName  = '';
    #[OA\Property(
        description: 'Returns the type of URI and location of the speech synthesis service for this voice.',
        nullable: false
    )]
    protected string $voiceUri      = '';

    #[OA\Property(
        description: 'Returns the information URI of the speech synthesis service for this voice.',
        nullable: true
    )]
    protected ?string $voiceInfoUri = null;

    public function isDefault(): bool
    {
        return $this->getAttribute('default', $this->default);
    }

    public function getLang(): string
    {
        return $this->getAttribute('lang', $this->lang);
    }

    public function isLocalService(): bool
    {
        return $this->getAttribute('localService', $this->localService);
    }

    public function getFriendlyName(): string
    {
        return $this->getAttribute('friendlyName', $this->friendlyName);
    }

    public function getVoiceInfoUri(): ?string
    {
        return $this->getAttribute('voiceInfoUri', $this->voiceInfoUri);
    }

    public function getName(): string
    {
        return $this->getAttribute('name', $this->name);
    }

    public function getVoiceUri(): string
    {
        return $this->getAttribute('voiceUri', $this->voiceUri);
    }

    public function setLang(string $lang): static
    {
        return $this->setAttribute('lang', $lang);
    }

    public function setName(string $name): static
    {
        return $this->setAttribute('name', $name);
    }

    public function setVoiceUri(string $voiceUri): static
    {
        return $this->setAttribute('voiceUri', $voiceUri);
    }

    public function setDefault(bool $default): static
    {
        return $this->setAttribute('default', $default);
    }

    public function setFriendlyName(string $friendlyName): static
    {
        return $this->setAttribute('friendlyName', $friendlyName);
    }

    public function setVoiceInfoUri(?string $voiceInfoUri): SpeechSynthesisVoice
    {
        return $this->setAttribute('voiceInfoUri', $voiceInfoUri);
    }
}
