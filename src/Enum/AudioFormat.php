<?php

namespace Enum;

enum AudioFormat: string
{
    case PCM = 'wav';
    case MP3 = 'mp3';
    case OGG = 'ogg';

    public function addExtension(string $file): string
    {
        $ext = '.' . $this->value;

        if ( ! str_ends_with(strtolower($file), $ext))
        {
            $file .= $ext;
        }
        return $file;
    }

    public function mime(): string
    {
        return match ($this)
        {
            self::PCM => 'audio/wav',
            self::MP3 => 'audio/mpeg',
            self::OGG => 'audio/ogg',
        };
    }
}
