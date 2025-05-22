<?php

namespace Controller;

use Model\SpeechSynthesisVoice;

class VoiceController
{
    /**
     * @param SpeechSynthesisVoice[] $all
     *
     * @return SpeechSynthesisVoice[]
     */
    protected function filterVoices(array $all): array
    {
        $languages = array_filter(explode(',', \Env::getItem('LANGUAGES')), 'trim');

        $result    = [];

        foreach ($all as $item)
        {
            foreach ($languages as $filter)
            {
                $filter = strtolower($filter);

                if ('*' === $filter || str_contains(strtolower($item->getLang()), $filter))
                {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }
}
