<?php

namespace Command;

use NGSOFT\Console\Profile\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

use function Laravel\Prompts\text;

#[AsCommand('app:hello', 'says hello')]
class HelloCommand extends Command
{
    use CommandTrait;

    protected function configure()
    {
        $this->addArgument('name', mode: InputArgument::OPTIONAL);
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        $name = $input->getArgument('name') ?: text(
            __('Please enter your name'),
            default: __('world'),
            required: true
        );

        if ('toto' === strtolower($name))
        {
            throw new \RuntimeException($name . ' ' . __('is not a valid name.'));
        }

        $this->log(__('selected name') . ': ' . $name);

        $io->info(sprintf("<options=bold>%s <amber-500>{$name}</> !", __('Hello')));
    }
}
