<?php

declare(strict_types=1);

namespace Provider;

use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisInterface;
use SpeechSynthesis\SpeechSynthesisResult;
use SpeechSynthesis\SpeechSynthesisUtterance;
use SpeechSynthesis\SpeechSynthesisVoice;
use Traits\ErrorLoggerTrait;
use Worker\PidLock;

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
            $provider && $this->add($provider);
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

    public function getVoices(?string $lang = null, ?string $provider = null): array
    {
        $result = [];

        $index  = 0;

        foreach ($this->providers as $service)
        {
            if ( ! $provider || $provider === $service->getName())
            {
                foreach ($service->getVoices($lang) as $voice)
                {
                    $name                      = $voice->getFriendlyName();
                    $result["{$name}{$index}"] = $voice;
                    ++$index;
                }
            }
        }
        ksort($result);

        return array_values($result);
    }

    public function getVoice(string $name, ?string $lang = null, ?string $useProvider = null): ?SpeechSynthesisVoice
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
                    if ( ! $lang || $lang === $voice->getLang())
                    {
                        return $voice;
                    }
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

    /**
     * @return string[]
     */
    public function listProviders(): array
    {
        $result = [];

        foreach ($this->providers as $provider)
        {
            $result[$provider->getName()] = $provider->getName();
        }
        return array_values($result);
    }

    public function prune(\DateTimeInterface $before)
    {
        static $lock = 'prune-synthesis';
        PidLock::lock($lock, 60);

        foreach ($this->providers as $provider)
        {
            $provider->prune($before);
        }
        PidLock::unlock($lock);
    }
}
