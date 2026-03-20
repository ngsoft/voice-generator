<?php

namespace Traits;

use Psr\Log\LoggerInterface;

trait ErrorLoggerTrait
{
    protected function logError(\Throwable $error, ?LoggerInterface $logger = null): static
    {
        $logger ??= \Services::make(LoggerInterface::class);

        $message = str_format('%s:%s %s(%s)', [
            basename($error->getFile()), $error->getLine(),
            get_class($error), $error->getMessage(),
        ]);

        if (env_get('APP_DEBUG', false))
        {
            $message .= str_format("\ntrace:%s", [
                $error->getTraceAsString(),
            ]);
        }
        $logger->error($message);
        return $this;
    }
}
