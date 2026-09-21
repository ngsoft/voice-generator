<?php

namespace Command;

use Message\SpeakMessage;
use NGSOFT\Console\Profile\CommandHelper;
use Provider\SynthesisProviderStack;
use Psr\SimpleCache\CacheInterface;
use Service\LoggerService;
use Service\VoicePlayerService;
use SpeechSynthesis\SpeechSynthesisException;
use SpeechSynthesis\SpeechSynthesisUtterance;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Process\Process;
use Traits\CommandTrait;

#[AsCommand('speak', 'Generate audio and speak')]
class SpeakCommand extends Command
{
    use CommandTrait;

    public const CACHE_DURATION = 60;

    private const CACHE_KEY     = 'speak.command.previous';

    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('text', mode: InputArgument::REQUIRED);
        $this->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Lang to use', 'en-US');
        $this->addOption('voice', null, InputOption::VALUE_OPTIONAL, 'Voice to use', 'en-US-AvaMultilingualNeural');
        $this->addOption('async', null, InputOption::VALUE_NONE, 'Queue the synthesis for a background worker instead of playing locally');
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $text      = $input->getArgument('text');

        if ( ! $text)
        {
            $io->error('Text argument is empty');
        }

        if ($input->getOption('async'))
        {
            $this->dispatchAsync($text, $input);
            $this->log('queued async synthesis: %s', [$text]);
            $io->success('Queued for async synthesis.');
            return self::SUCCESS;
        }

        $said      = $this->previousTextPlayed();

        if (in_array($text, $said))
        {
            $this->log('ignoring repeated text (%s) in %d sec interval', [$text, self::CACHE_DURATION], LoggerService::WARN);
            $io->writeln($text);
            return self::SUCCESS;
        }

        $messenger = env_get('SPEAK_COMMAND_USES_MESSENGER', false);
        $lang      = $io->getInput()->getOption('lang');
        $voice     = $io->getInput()->getOption('voice');
        $format    = 'mp3';

        if ($messenger)
        {
            $this->log('Using async messenger to play message');
            $this->dispatchAsync($text, $input);
            $io->writeln($text);
            return self::SUCCESS;
        }

        $proc      = null;
        // $voice = "en-US-AvaMultilingualNeural";
        // $lang = "en-US";

        try
        {
            if ('\\' === DIRECTORY_SEPARATOR)
            {
                $result = $this->synthesisProviderStack->speak(
                    SpeechSynthesisUtterance::make(compact('voice', 'text', 'lang', 'format'))
                );

                if ($result->path)
                {
                    try
                    {
                        $proc = VoicePlayerService::playSound($result->path);
                    } finally
                    {
                        @unlink($result->path);
                    }
                }
            } elseif ('Darwin' === PHP_OS)
            {
                $result = $this->synthesisProviderStack->speak(
                    SpeechSynthesisUtterance::make(compact('voice', 'text', 'lang', 'format'))
                );

                if ($result->path)
                {
                    try
                    {
                        $proc = VoicePlayerService::playSound($result->path);
                    } finally
                    {
                        @unlink($result->path);
                    }
                }
            } elseif ((bool) env_get('WSL_DISTRO_NAME'))
            {
                // replay command under WSL
                $proc = Process::fromShellCommandline(
                    sprintf('php.exe bin/console speak "%s" --voice "%s" --lang "%s"', $text, $voice, $lang)
                );

                $proc->run();

                if ($proc->isSuccessful())
                {
                    $io->writeln($text);
                    return self::SUCCESS;
                }
                return self::FAILURE;
            }

            $code = $proc?->getExitCode();

            if (0 === $code)
            {
                $said[time()] = $text;
                $this->cache->set('speak.command.previous', $said);
                $io->writeln($text);
            }

            return $code ?? self::FAILURE;
        } catch (SpeechSynthesisException)
        {
            // Retry in messenger
            $this->dispatchAsync($text, $input);
            $io->writeln($text);
            return self::SUCCESS;
        }
    }

    private function dispatchAsync(string $text, InputInterface $input)
    {
        $this->bus->dispatch(new Envelope(
            new SpeakMessage(
                $text,
                (string) $input->getOption('voice'),
                (string) $input->getOption('lang')
            ),
            [new TransportNamesStamp(['async'])]
        ));
    }

    /**
     * @return string[]
     */
    private function previousTextPlayed(): array
    {
        $result = [];
        $limit  = time() - self::CACHE_DURATION;

        if ($values = $this->cache->get('speak.command.previous', []))
        {
            foreach ($values as $tt => $value)
            {
                if ($tt < $limit)
                {
                    continue;
                }
                $result[$tt] = $value;
            }
        }

        return $result;
    }
}
