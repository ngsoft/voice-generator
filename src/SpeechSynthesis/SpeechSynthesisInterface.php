<?php

namespace SpeechSynthesis;

/**
 * @see https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesis
 */
interface SpeechSynthesisInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param SpeechSynthesisUtterance $utterance
     *
     * @return SpeechSynthesisResult
     *
     * @throws SpeechSynthesisException when an error ocurred
     */
    public function speak(SpeechSynthesisUtterance $utterance): SpeechSynthesisResult;

    /**
     * @return SpeechSynthesisVoice[]
     */
    public function getVoices(?string $lang = null): array;

    public function hasVoice(string $name): bool;

    public function getFile(string $identifier): ?\FileResponseView;

    public function prune(\DateTimeInterface $before);
}
