<?php

declare(strict_types=1);

namespace Provider;

use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisInterface;
use SpeechSynthesis\SpeechSynthesisResult;
use SpeechSynthesis\SpeechSynthesisUtterance;
use SpeechSynthesis\SpeechSynthesisVoice;
use Traits\ErrorLoggerTrait;

final class SynthesisProviderStack
{
    use ErrorLoggerTrait;

    /**
     * @var SpeechSynthesisInterface[]
     */
    private $providers = [];

    /**
     * @param SpeechSynthesisInterface[] $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider)
        {
            $this->add($provider);
        }
    }

    public function add(SpeechSynthesisInterface $provider): static
    {
        if ( ! in_array($provider, $this->providers, true))
        {
            $this->providers[] = $provider;
        }
        return $this;
    }

    public function speak(SpeechSynthesisUtterance $utterance): SpeechSynthesisResult
    {
        foreach ($this->providers as $provider)
        {
            if ($provider->hasVoice($utterance->getVoice()))
            {
                return $provider->speak($utterance);
            }
        }
        throw new SpeechSynthesisException('No provider found for this utterance.');
    }

    public function getVoices(?string $lang = null): array
    {
        $result = [];

        foreach ($this->providers as $provider)
        {
            $result = array_merge($result, $provider->getVoices($lang));
        }
        return $result;
    }

    public function getVoice(string $name, ?string $useProvider = null): ?SpeechSynthesisVoice
    {
        $providers = array_filter(
            $this->providers,
            fn (SpeechSynthesisInterface $provider) => $useProvider === $provider->getName()
        );

        foreach ($providers as $provider)
        {
            foreach ($provider->getVoices() as $voice)
            {
                if ($name === $voice->getName())
                {
                    return $voice;
                }
            }
        }
        return null;
    }

    public function getFile(string $identifier): ?\FileResponseView
    {
        foreach ($this->providers as $provider)
        {
            if ($result = $provider->getFile($identifier))
            {
                return $result;
            }
        }
        return null;
    }
}
