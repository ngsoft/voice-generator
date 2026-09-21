<?php

namespace Service;

use Symfony\Component\Process\Process;

readonly class VoicePlayerService
{
    /**
     * @param string $path
     *
     * @return null|Process
     */
    public static function playSound(string $path): ?Process
    {
        if ('\\' === DIRECTORY_SEPARATOR)
        {
            $proc = Process::fromShellCommandline(
                sprintf('"%s/cmdmp3.exe" "%s"', resolve_path('%project_root%/bin'), $path)
            );

            $proc->run();
            return $proc;
        }

        if ('Darwin' === PHP_OS)
        {
            $proc = Process::fromShellCommandline(
                sprintf('afplay "%s"', $path)
            );
            $proc->run();

            return $proc;
        }
        return null;
    }
}
