<?php

declare(strict_types=1);

namespace MessageHandler;

use Command\SpeakCommand;
use Message\SpeakMessage;
use Provider\SynthesisProviderStack;
use Psr\SimpleCache\CacheInterface;
use Service\LoggerService;
use Service\VoicePlayerService;
use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisUtterance;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final readonly class SpeakMessageHandler
{
    public function __construct(
        private SynthesisProviderStack $synthesisProviderStack,
        private LoggerService $logger,
        private MessageBusInterface $bus,
        private CacheInterface $cache,
    ) {}

    public function __invoke(SpeakMessage $message): ?string
    {
        try
        {
            set_time_limit(0);
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

            $this->logger->log(LoggerService::INFO, sprintf('async synthesis stored: %s', $result->path));

            $proc   = VoicePlayerService::playSound($result->path);

            if ($proc?->isSuccessful())
            {
                $this->addToCache($message);
                @unlink($result->path);
            }
            return $result->path;
        } catch (SpeechSynthesisException)
        {
            $this->logger->warning(sprintf('async synthesis failed: %s', $message->text));
            $this->dispatchAsync($message);
            return null;
        }
    }

    private function dispatchAsync(SpeakMessage $message)
    {
        sleep(5);
        $this->bus->dispatch(new Envelope(
            $message,
            [new TransportNamesStamp(['async'])]
        ));
    }

    private function addToCache(SpeakMessage $message)
    {
        $said         = [];

        $limit        = time() - SpeakCommand::CACHE_DURATION;

        if ($values = $this->cache->get('speak.command.previous', []))
        {
            foreach ($values as $tt => $value)
            {
                if ($tt < $limit)
                {
                    continue;
                }
                $said[$tt] = $value;
            }
        }
        $said[time()] = $message->text;
        $this->cache->set('speak.command.previous', $said);
    }
}
