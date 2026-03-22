<?php

declare(strict_types=1);

namespace SpeechSynthesis;

use Enum\AudioFormat;
use Model\DataModel;
use OpenApi\Attributes as OA;
use Sql\ValidationError;

/**
 * @see https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesisUtterance/SpeechSynthesisUtterance
 */
#[OA\Schema]
class SpeechSynthesisUtterance extends DataModel
{
    #[OA\Property(description: 'A string containing the text that will be synthesized when the utterance is spoken.', nullable: false)]
    protected string $text         = '';
    #[OA\Property(description: 'Language of the utterance.', example: 'fr-FR', nullable: false)]
    protected string $lang         = '';
    #[OA\Property(description: 'Voice that will be used to speak the utterance.', nullable: false)]
    protected string $voice        = '';

    #[OA\Property(description: 'Speed at which the utterance will be spoken at.', nullable: true, maximum: 10.0, minimum: 0.1)]
    protected float $rate          = 1.0;
    #[OA\Property(description: 'Pitch at which the utterance will be spoken at.', nullable: true, maximum: 2.0, minimum: 0.0)]
    protected float $pitch         = 1.0;

    #[OA\Property(description: 'Pitch at which the utterance will be spoken at.', nullable: true, maximum: 2.0, minimum: 0.0)]
    protected float $volume        = 1.0;

    #[OA\Property(description: 'Audio format.', type: 'string', nullable: true, enum: AudioFormat::class)]
    protected ?AudioFormat $format = null;

    public function getText(): string
    {
        return $this->text;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getVoice(): string
    {
        return $this->voice;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getPitch(): float
    {
        return $this->pitch;
    }

    public function getVolume(): float
    {
        return $this->volume;
    }

    public function getFormat(): ?AudioFormat
    {
        return $this->format;
    }

    protected function validateData(array $data)
    {
        if ( ! $this->checkRequired($data, 'text', 'lang', 'voice'))
        {
            return;
        }

        if (empty($data['text']))
        {
            throw ValidationError::make('text cannot be empty');
        }

        if (isset($data['pitch']))
        {
            if ( ! is_numeric($data['pitch']))
            {
                throw ValidationError::make('pitch must be a number');
            }
            $data['pitch'] = (float) $data['pitch'];

            if (0.0 > $data['pitch'] || 2.0 < $data['pitch'])
            {
                throw ValidationError::make('rate must be between 0.0 and 2.0');
            }
        }

        if (isset($data['rate']))
        {
            if ( ! is_numeric($data['rate']))
            {
                throw ValidationError::make('rate must be a number');
            }

            $data['rate'] = (float) $data['rate'];

            if (0.1 > $data['rate'] || 10 < $data['rate'])
            {
                throw ValidationError::make('rate must be between 0.1 and 10.0');
            }
        }

        if (isset($data['volume']))
        {
            if ( ! is_numeric($data['volume']))
            {
                throw ValidationError::make('volume must be a number');
            }

            $data['volume'] = (float) $data['volume'];

            if (0.0 > $data['volume'] || 2.0 < $data['volume'])
            {
                throw ValidationError::make('volume must be between 0.0 and 2.0');
            }
        }

        if (isset($data['format']) && ! ($data['format'] = AudioFormat::tryFrom($data['format'])))
        {
            throw ValidationError::make('format must includes %s', $this->getEnumValues(AudioFormat::class));
        }

        parent::validateData($data);
    }
}
