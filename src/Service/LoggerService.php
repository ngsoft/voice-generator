<?php

declare(strict_types=1);

namespace Service;

use Psr\Log\LoggerInterface;

class LoggerService implements LoggerInterface
{
    public const LOG                = 'log';
    public const EMERGENCY          = 'emergency';
    public const ALERT              = 'alert';
    public const CRIT               = 'critical';
    public const ERR                = 'err';
    public const WARN               = 'warn';
    public const NOTICE             = 'notice';
    public const INFO               = 'info';
    public const DEBUG              = 'debug';

    private bool $backtrace         = false;
    private string $prefix          = '';

    private array $ignore_backtrace = ['CommandTrait.php', 'ErrorLoggerTrait.php'];

    public function __construct(private ?\ApplicationLogger $logger = null)
    {
        if ( ! $this->logger)
        {
            $this->logger = \ApplicationLogger::getLogger();
        }
        $this->backtrace = \ApplicationLogger::hasBacktrace();
        $this->prefix    = ltrim($this->logger->getPrefix() . ' ');
    }

    public function addIgnoreBacktrace(string ...$files): void
    {
        $this->ignore_backtrace = [...$this->ignore_backtrace, ...$files];
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->callLogger(__FUNCTION__, $this->processMessage($message, $context));
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->callLogger($level, $this->processMessage($message, $context));
    }

    public function hasBacktrace(): bool
    {
        return $this->backtrace;
    }

    public function setBacktrace(bool $backtrace): static
    {
        $this->backtrace = $backtrace;
        return $this;
    }

    private function callLogger(string $method, string $message)
    {
        $method = match ($method)
        {
            self::EMERGENCY,
            self::ALERT => self::CRIT,
            default     => $method
        };

        if (self::DEBUG === $method && ! env_get('APP_DEBUG'))
        {
            return;
        }

        try
        {
            \ApplicationLogger::setBackTrace(false);
            $this->logger->setPrefix('');
            $this->logger->log(
                $this->addBacktrace(self::LOG !== $method ? $this->addLevel($method, $message) : $message)
            );
        } finally
        {
            $this->logger->setPrefix($this->prefix);
            \ApplicationLogger::setBackTrace($this->backtrace);
        }
    }

    /**
     * Overrides ApplicationLogger to display backtrace and prefix correctly.
     */
    private function addBacktrace(string $message): string
    {
        $metadata = '';
        $prefix   = $this->prefix;

        if ($this->backtrace)
        {
            $ignore_backtrace = [__FILE__, ...$this->ignore_backtrace];

            foreach (@debug_backtrace() as $trace)
            {
                $file = $trace['file'];

                foreach ($ignore_backtrace as $ignore)
                {
                    if (str_contains($file, $ignore))
                    {
                        continue 2;
                    }
                }

                $metadata .= sprintf('%s:%s ', basename($trace['file']), $trace['line']);
                break;
            }
        }
        return sprintf('%s%s%s', $prefix, $metadata, $message);
    }

    private function addLevel(string $level, string $message): string
    {
        return sprintf('%s: %s', strtoupper($level), $message);
    }

    private function processMessage(string|\Stringable $message, array $context): string
    {
        return str_format((string) $message, $context);
    }
}
