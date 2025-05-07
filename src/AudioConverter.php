<?php

use Symfony\Component\Process\Process;

class AudioConverter
{
    public static function convert(string $input, string $output): bool
    {
        if ($input === $output)
        {
            return is_file($input);
        }

        $proc = Process::fromShellCommandline(sprintf(
            'ffmpeg -i "%s" -acodec pcm_s16le -ar 16000 "%s"',
            $input,
            $output
        ));

        $proc->run();

        return $proc->isSuccessful() && is_file($output);
    }
}
