<?php

namespace Traits;

use NGSOFT\Console\Profile\CommandHelper;
use NGSOFT\Console\Profile\Traits\HasCommandHelper;
use Service\LoggerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait CommandTrait
{
    use HasCommandHelper;

    private ?LoggerService $logger;

    #[Required]
    public function setLogger(LoggerService $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param CommandHelper  $io
     * @param InputInterface $input
     *
     * @return mixed|void
     */
    abstract protected function executeCommand(CommandHelper $io, InputInterface $input);

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandHelper = new CommandHelper($this->input = $input, $this->output = $output);

        try
        {
            $result = $this->executeCommand($this->commandHelper, $input) ?? Command::SUCCESS;
        } catch (\Throwable $error)
        {
            $result = $this->logError($error);
        }

        $result              = ! is_int($result) ? Command::INVALID : $result;
        \Config::setItem('command.execution.status', $result);
        return $result;
    }

    protected function logError(\Throwable $error): int
    {
        $backtrace = $this->logger->hasBacktrace();
        $this->logger->setBacktrace(false);

        if (env_get('APP_DEBUG', false))
        {
            $this->logger->error("%s:%s %s(%s)\ntrace: %s", [
                basename($error->getFile()), $error->getLine(),
                get_class($error), $error->getMessage(), $error->getTraceAsString()]);
        } else
        {
            $this->logger->error('%s:%s %s(%s)', [
                basename($error->getFile()), $error->getLine(),
                get_class($error), $error->getMessage()]);
        }

        $this->logger->setBacktrace($backtrace);
        $this->commandHelper->error($error->getMessage());
        return Command::FAILURE;
    }

    protected function log(string|\Stringable $message, array $replacements = [], string $level = LoggerService::INFO): void
    {
        $this->logger?->log($level, str_format((string) $message, $replacements));
    }

    protected function logAndPrint(string|\Stringable $message, array $replacements = [], string $level = LoggerService::INFO): void
    {
        $this->log($message, $replacements, $level);
        $message = str_format((string) $message, $replacements);

        switch ($level)
        {
            case LoggerService::WARN:
                $this->getCommandHelper()->warning($message);
                break;
            case LoggerService::ERR:
            case LoggerService::CRIT:
            case LoggerService::ALERT:
                $this->getCommandHelper()->error($message);
                break;
            case LoggerService::INFO:
            case LoggerService::NOTICE:
                $this->getCommandHelper()->info($message);
                break;
        }
    }
}
