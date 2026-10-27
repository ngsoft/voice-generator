<?php

namespace Command;

use Enum\AudioFormat;
use NGSOFT\Console\Profile\CommandHelper;
use Psr\SimpleCache\CacheInterface;
use Service\LoggerService;
use Service\VoicePlayerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Traits\CommandTrait;

#[AsCommand('speak:remote', 'Generate audio via a remote voice-generator instance and speak')]
class SpeakRemoteCommand extends Command
{
    use CommandTrait;

    public const CACHE_DURATION = 60;

    private const CACHE_KEY     = 'speak.remote.command.previous';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('text', mode: InputArgument::REQUIRED);
        $this->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Lang to use', 'en-US');
        $this->addOption('voice', null, InputOption::VALUE_OPTIONAL, 'Voice to use', 'en-US-AvaMultilingualNeural');
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $text    = (string) $input->getArgument('text');

        if ('' === $text)
        {
            $io->error('Text argument is empty');
            return self::FAILURE;
        }

        $baseUrl = rtrim((string) env_get('SPEAK_REMOTE_URL', '', false), '/');
        $apiKey  = (string) env_get('SPEAK_REMOTE_API_KEY', '', false);

        if ('' === $baseUrl)
        {
            $io->error('SPEAK_REMOTE_URL is not configured');
            return self::FAILURE;
        }

        if ( ! str_starts_with($baseUrl, 'https://') && ! str_starts_with($baseUrl, 'http://'))
        {
            $io->error('SPEAK_REMOTE_URL must be an http(s) URL');
            return self::FAILURE;
        }

        $said    = $this->previousTextPlayed();

        if (in_array($text, $said, true))
        {
            $this->log('ignoring repeated text (%s) in %d sec interval', [$text, self::CACHE_DURATION], LoggerService::WARN);
            $io->writeln($text);
            return self::SUCCESS;
        }

        $lang    = (string) $input->getOption('lang');
        $voice   = (string) $input->getOption('voice');

        if ((bool) env_get('WSL_DISTRO_NAME') && '\\' !== DIRECTORY_SEPARATOR)
        {
            $proc = Process::fromShellCommandline(
                sprintf(
                    'php.exe bin/console speak:remote %s --voice %s --lang %s',
                    escapeshellarg($text),
                    escapeshellarg($voice),
                    escapeshellarg($lang)
                )
            );
            $proc->run();

            if ($proc->isSuccessful())
            {
                $io->writeln($text);
                return self::SUCCESS;
            }

            return self::FAILURE;
        }

        $path    = $this->downloadRemoteAudio($baseUrl, $apiKey, $text, $lang, $voice);

        if (null === $path)
        {
            $io->error('Remote synthesis failed');
            return self::FAILURE;
        }

        $this->log('remote audio saved: %s', [$path]);

        $proc    = VoicePlayerService::playSound($path);
        $code    = $proc?->getExitCode();

        if (0 === $code)
        {
            $said[time()] = $text;
            $this->cache->set(self::CACHE_KEY, $said);
            $io->writeln($text);
        }

        return $code ?? self::FAILURE;
    }

    private function downloadRemoteAudio(
        string $baseUrl,
        string $apiKey,
        string $text,
        string $lang,
        string $voice,
    ): ?string {
        $format  = AudioFormat::MP3;
        $storage = resolve_path('%data%/remote');
        @mkdir($storage, 0777, true);

        $file    = $format->addExtension(resolve_path($storage, generate_v4()));

        $headers = [
            'Accept'       => $format->mime(),
            'Content-Type' => 'application/json',
        ];

        if ('' !== $apiKey)
        {
            $headers['X-Api-Key'] = $apiKey;
        }

        $url     = $baseUrl . '/api/speak';
        $timeout = (int) env_get('SPEECH_SYNTHESIS_SOCKET_TIMEOUT', 60);

        $this->log('remote speak POST %s (voice=%s lang=%s timeout=%ds)', [$url, $voice, $lang, $timeout]);

        $resp    = \CurlHandler::makeHttpRequest(
            $url,
            json_encode([
                'text'   => $text,
                'lang'   => $lang,
                'voice'  => $voice,
                'format' => $format->value,
            ], JSON_UNESCAPED_UNICODE),
            'POST',
            $headers,
            $timeout
        );

        if (200 !== $resp->status)
        {
            $message = 'HTTP ' . $resp->status;

            if (is_array($data = $resp->getDecodedContents()) && ($msg = var_get('message', $data)))
            {
                $message .= ': ' . $msg;
            }

            $this->log('remote speak failed %s: %s', [$url, $message], LoggerService::ERR);
            return null;
        }

        $content = $resp->getContents();

        $this->log('remote speak OK %s -> %d bytes', [$url, strlen((string) $content)]);

        if ( ! $content || ! @file_put_contents($file, $content))
        {
            $this->log('cannot write remote audio to %s', [$file], LoggerService::ERR);
            return null;
        }

        return $file;
    }

    /**
     * @return string[]
     */
    private function previousTextPlayed(): array
    {
        $result = [];
        $limit  = time() - self::CACHE_DURATION;

        if ($values = $this->cache->get(self::CACHE_KEY, []))
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
