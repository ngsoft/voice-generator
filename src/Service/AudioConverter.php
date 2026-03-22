<?php

namespace Service;

use Symfony\Component\Process\Process;

readonly class AudioConverter
{
    public static function convert(string $input, string $output, bool $eraseInput = false): bool
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

        if ( ! $proc->isSuccessful())
        {
            \ApplicationLogger::getLogger()->warn('ffmpeg conversion failed: %s', [
                trim($proc->getErrorOutput()),
            ]);
        }

        if ($proc->isSuccessful() && is_file($output))
        {
            $eraseInput && @unlink($input);
            return true;
        }
        @unlink($output);
        return false;
    }

    public static function convertOgg(string $input, string $output, bool $eraseInput = false): bool
    {
        if ($input === $output)
        {
            return is_file($input);
        }

        $proc = Process::fromShellCommandline(sprintf(
            'ffmpeg -i "%s" -acodec libvorbis -ar 16000 "%s"',
            $input,
            $output
        ));
        $proc->run();

        if ( ! $proc->isSuccessful())
        {
            \Services::getLogger()->warn('ffmpeg conversion failed: %s', [
                trim($proc->getErrorOutput()),
            ]);
        }

        if ($proc->isSuccessful() && is_file($output))
        {
            $eraseInput && @unlink($input);
            return true;
        }
        @unlink($output);
        return false;
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

            if ( ! $proc->isSuccessful())
            {
                \Services::getLogger()->warn('ffprobe failed: %s', [
                    trim($proc->getErrorOutput()),
                ]);
            }

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
