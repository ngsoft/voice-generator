<?php

namespace Service;

readonly class WorkerService
{
    public function launch(string $worker, string $arguments = '', $php = 'php'): void
    {
        if ('\\' === DIRECTORY_SEPARATOR)
        {
            pclose(popen(sprintf(
                'start "" "%s" "%s"%s',
                $php,
                $worker,
                $arguments ? " {$arguments}" : ''
            ), 'rb'));
        } else
        {
            @exec(sprintf(
                '/usr/bin/env "%s" "%s"%s &>> /dev/null &',
                $php,
                $worker,
                $arguments ? " {$arguments}" : ''
            ));
        }
    }
}
