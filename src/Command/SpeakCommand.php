<?php

namespace Command;

use NGSOFT\Console\Profile\CommandHelper;
use Provider\SynthesisProviderStack;
use SpeechSynthesis\SpeechSynthesisUtterance;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;
use Traits\CommandTrait;

#[AsCommand('speak', 'Generate audio and speak')]
class SpeakCommand extends Command
{
    use CommandTrait;

    public function __construct(private readonly SynthesisProviderStack $synthesisProviderStack)
    {
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
        $text   = $input->getArgument('text');

        if ( ! $text)
        {
            $io->error('Text argument is empty');
        }

        $lang   = $io->getInput()->getOption('lang');
        $voice  = $io->getInput()->getOption('voice');

        //        $voice = "en-US-AvaMultilingualNeural";
        //        $lang = "en-US";
        $format = 'mp3';

        if ('\\' === DIRECTORY_SEPARATOR)
        {
            $result = $this->synthesisProviderStack->speak(
                SpeechSynthesisUtterance::make(compact('voice', 'text', 'lang', 'format'))
            );

            if ($result->path)
            {
                try
                {
                    $proc = Process::fromShellCommandline(
                        sprintf('"%s/cmdmp3.exe" "%s"', resolve_path('%project_root%/bin'), $result->path)
                    );
                    $proc->run();
                    return $proc->getExitCode();
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
                    $proc = Process::fromShellCommandline(
                        sprintf('afplay "%s"', $result->path)
                    );
                    $proc->run();
                    return $proc->getExitCode();
                } finally
                {
                    @unlink($result->path);
                }
            }
        }
        return self::FAILURE;
    }
}
