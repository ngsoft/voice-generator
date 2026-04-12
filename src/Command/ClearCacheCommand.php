<?php

namespace Command;

use NGSOFT\Console\Profile\CommandHelper;
use Provider\SynthesisProviderStack;
use Psr\Cache\CacheItemPoolInterface;
use Service\LoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

#[AsCommand('cache:clear', 'Clear cache')]
class ClearCacheCommand extends Command
{
    use CommandTrait;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ClearTranslationCacheCommand $translationCacheCommand,
        private readonly SynthesisProviderStack $providerStack
    ) {
        parent::__construct();
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $this->logAndPrint('Clearing translation cache');

        if ($this->translationCacheCommand->clearCache())
        {
            $io->success('Translation cache cleared');
        }
        $this->logAndPrint('Clearing application cache');

        if ($this->cache->clear())
        {
            $io->success('Application cache cleared');
        }

        $this->logAndPrint('Removing audio files');

        $this->providerStack->prune(date_create()->add(new \DateInterval('P1D')));

        $ok = true;

        foreach (iterate_files(resolve_path('%data%')) as $file)
        {
            if (in_array($file->getExtension(), ['mp3', 'wav', 'ogg']))
            {
                $ok = false;
                $this->logAndPrint('Audio file %s was not removed', [$file->getFilename()], LoggerService::WARN);
            }
        }

        if ($ok)
        {
            $io->success('Audio files removed');
        }

        return self::SUCCESS;
    }
}
