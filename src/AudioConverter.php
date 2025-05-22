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

    public static function getMediaDuration(string $input): float
    {
        if (is_file($input))
        {
            $proc = Process::fromShellCommandline(sprintf(
                'ffprobe -i "%s" -show_entries format=duration -v quiet -of csv="p=0"',
                $input
            ));

            $proc->run();

            if (is_numeric($number = trim($proc->getOutput())))
            {
                return round((float) $number, 6);
            }
        }

        return 0.0;
    }

    public static function secToTimeMicro(float $seconds): string
    {
        $hours       = floor($seconds / 3600);
        $seconds -= $hours   * 3600;
        $minutes     = floor($seconds / 60);
        $seconds -= $minutes * 60;

        $fullSeconds = floor($seconds);
        $seconds -= $fullSeconds;
        return sprintf('%02d:%02d:%02d.%06d', $hours, $minutes, $fullSeconds, round($seconds, 6) * 1000000);
    }
}
