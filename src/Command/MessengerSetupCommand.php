<?php

declare(strict_types=1);

namespace Command;

use Messenger\PdoStore;
use NGSOFT\Console\Profile\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

#[AsCommand('messenger:setup', 'Create the messenger transport table')]
class MessengerSetupCommand extends Command
{
    use CommandTrait;

    public function __construct(private readonly PdoStore $store)
    {
        parent::__construct();
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        if ($this->store->setup())
        {
            $io->success(sprintf('Messenger table ready (%s connection).', $this->store->connectionName()));
            return self::SUCCESS;
        }

        $io->error('Messenger table setup failed.');
        return self::FAILURE;
    }
}
