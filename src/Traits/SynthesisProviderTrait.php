<?php

namespace Traits;

use SpeechSynthesis\SpeechSynthesisVoice;

trait SynthesisProviderTrait
{
    use ErrorLoggerTrait;

    private readonly string $storage;

    public function prune(\DateTimeInterface $before)
    {
        $timestamp = $before->getTimestamp();

        foreach (iterate_files($this->storage) as $fileInfo)
        {
            if (in_array($fileInfo->getExtension(), ['mp3', 'wav', 'ogg']))
            {
                if ($timestamp > (int) $fileInfo->getMTime())
                {
                    @unlink($fileInfo->getPathname());
                }
            }
        }
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

    abstract public function getVoices(?string $lang = null): array;
}
