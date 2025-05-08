<?php

use Model\SpeechSynthesisVoice;
use NGSOFT\DataStructure\Map;

/** @var SpeechSynthesisVoice[] $voices */
$languages = new Map();

foreach ($voices as $voice)
{
    $lang   = $voice->getLang();
    $list   = $languages->get($lang) ?? [];
    $list[] = $voice;
    $languages->set($lang, $list);
}
$languages->lock();
extends_template('vite');
/* @block head */
ob_start(); ?>
<link rel="preload stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@latest/dist/css/tom-select.bootstrap4.css" as="style"
      media="all" crossorigin>

<link href="https://cdn.jsdelivr.net/npm/tom-select@latest/dist/css/tom-select.bootstrap4.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@latest/dist/js/tom-select.complete.min.js"></script>
<?php set_attr('head', ob_get_clean());

/* @block contents */ ?>

<div class="container py-4">
    <header class="flex items-center">
        <span class="logo ps-4 capitalize"><?= trim($title ?? Env::getItem('APP_TITLE', 'My App')); ?></span>
        <div class="ms-auto select-none">
            <input id="dark-mode-switch" type="checkbox" role="switch" class="inset round mb-0">
            <label for="dark-mode-switch" class="font-medium">Dark Mode</label>
        </div>

    </header>
    <main class="py-8">
        <form method="post" class="py-0">
            <h2>Voices</h2>

            <div class="flex flex-col mt-8">
                <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%] mb-3">
                    <label for="lang" class="md:w-[200px] inline-block">Select a language</label>
                    <select class="w-[90%] md:w-[320px] lg:w-[640px]" id="lang">
                        <option value="">Select a language</option>
                        <?php /** @var string $lang */
                        foreach ($languages->keys() as $lang): ?>
                            <option value="<?= $lang; ?>"><?= $lang; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%] mb-3">
                    <label for="voice" class="md:w-[200px] inline-block">Select a voice</label>
                    <select class="w-[90%] md:w-[320px] lg:w-[640px]" id="voice">
                        <option value="">Select a voice</option>
                        <?php foreach ($languages->entries() as $lang => $list): ?>
                            <optgroup label="<?= $lang; ?>">
                                <?php /** @var SpeechSynthesisVoice $voice */
                                foreach ($list as $voice): ?>
                                    <option value="<?= $voice->getVoiceUri(); ?>"><?= $voice->getName(); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%]">
                <label for="text" class="md:w-[200px] inline-block">Speak</label>
                <textarea id="text" class="w-[90%] lg:w-[640px] md:w-[320px] resize-none h-[120px]"
                          placeholder="Please input text to be said"></textarea>
            </div>

            <div class="fixed z-[-1] top-[-100vh] left-[-100vw]">
                <audio controls autoplay></audio>
            </div>


            <div class="mb-4 mt-8 flex justify-center gap-8">

                <button type="reset" class="secondary rounded px-8 py-4">
                    Reset
                </button>

                <button disabled type="button" id="download">
                    Download File
                </button>

                <button disabled type="submit" class="primary rounded px-8 py-4" id="submitButton">
                    Listen
                </button>
            </div>

        </form>
    </main>
</div>

