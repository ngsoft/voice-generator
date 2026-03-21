<?php

namespace SpeechSynthesis;

class SpeechSynthesisException extends \RuntimeException
{
    public static function make($message, ...$replacements)
    {
        return new static(str_format($message, $replacements));
    }
}
