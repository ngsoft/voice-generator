<?php

namespace View;

use OpenApi\Attributes as OA;
use SpeechSynthesis\SpeechSynthesisVoice;

#[OA\Schema]
class VoiceListResponse extends PaginatedResponse
{
    /**
     * @var SpeechSynthesisVoice[]
     */
    #[OA\Property(description: 'Voice list')]
    protected array $voices;
}
