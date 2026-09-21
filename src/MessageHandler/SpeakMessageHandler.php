<?php

declare(strict_types=1);

namespace MessageHandler;

use Message\SpeakMessage;
use Provider\SynthesisProviderStack;
use Service\LoggerService;
use SpeechSynthesis\SpeechSynthesisUtterance;

final class SpeakMessageHandler
{
    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
        private readonly LoggerService $logger,
    ) {}

    public function __invoke(SpeakMessage $message): ?string
    {
        $result = $this->synthesisProviderStack->speak(
            SpeechSynthesisUtterance::make([
                'voice'  => $message->voice,
                'text'   => $message->text,
                'lang'   => $message->lang,
                'format' => $message->format,
            ])
        );

        if ( ! $result->path || ! is_file($result->path))
        {
            $this->logger->log(LoggerService::ERR, sprintf('async synthesis produced no audio for: %s', $message->text));
            return null;
        }

        $dir = resolve_path('%data%/tts');

        if ( ! is_dir($dir))
        {
            @mkdir($dir, 0o775, true);
        }

        $dest = $dir . DIRECTORY_SEPARATOR
            . sha1(implode('|', [$message->voice, $message->lang, $message->text]))
            . '.' . $message->format;

        if ( ! @rename($result->path, $dest))
        {
            @copy($result->path, $dest);
            @unlink($result->path);
        }

        if ( ! is_file($dest))
        {
            $this->logger->log(LoggerService::ERR, sprintf('async synthesis failed to persist: %s', $dest));
            return null;
        }

        $this->logger->log(LoggerService::INFO, sprintf('async synthesis stored: %s', $dest));

        return $dest;
    }
}
