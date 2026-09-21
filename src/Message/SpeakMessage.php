<?php

declare(strict_types=1);

namespace Message;

final readonly class SpeakMessage
{
    public function __construct(
        public string $text,
        public string $voice = 'en-US-AvaMultilingualNeural',
        public string $lang = 'en-US',
        public string $format = 'mp3',
    ) {}
}
