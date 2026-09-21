<?php

declare(strict_types=1);

namespace Command;

use Messenger\PdoTransport;
use NGSOFT\Console\Profile\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Traits\CommandTrait;
use Worker\PidLock;

#[AsCommand('messenger:consume', 'Consume queued async messages (e.g. speech synthesis)')]
class MessengerConsumeCommand extends Command
{
    use CommandTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly PdoTransport $transport,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Stop after N messages (0 = unlimited)', '0');
        $this->addOption('time-limit', null, InputOption::VALUE_OPTIONAL, 'Stop after N seconds (0 = unlimited)', '0');
        $this->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Microseconds to sleep when the queue is empty', '1000000');
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        if ( ! PidLock::lock('messenger', 1))
        {
            $io->warning('A messenger worker is already running.');
            return self::SUCCESS;
        }

        try
        {
            $limit     = (int) $input->getOption('limit');
            $timeLimit = (int) $input->getOption('time-limit');
            $sleep     = (int) $input->getOption('sleep');

            $dispatcher = new EventDispatcher();

            if ($limit > 0)
            {
                $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
            }

            if ($timeLimit > 0)
            {
                $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));
            }

            $worker = new Worker(['async' => $this->transport], $this->bus, $dispatcher, $this->logger);

            $io->info('Consuming messages. Press CTRL+C to stop.');

            $worker->run(['sleep' => max(0, $sleep)]);
        } finally
        {
            PidLock::unlock('messenger');
        }

        return self::SUCCESS;
    }
}
