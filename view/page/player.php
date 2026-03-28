<?php
extend('vite');
title('Speech Synthesis Player');
?>
<header>
    <span class="logo ps-4 capitalize"><?= trim($title ?? env_get('APP_TITLE', 'My App', false)); ?></span>
    <div class="ms-auto select-none">
        <input id="dark-mode-switch" type="checkbox" role="switch" class="inset round mb-0">
        <label for="dark-mode-switch" class="font-medium">Dark Mode</label>
    </div>
</header>
<main>


    <form method="post" class="py-0 select-none px-[2%]" id="player-form">
        <h2 class="">Voices</h2>

        <div class="flex flex-col mt-8">
            <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%] mb-3">
                <label for="provider" class="md:w-[200px] inline-block">Select a provider</label>
                <select class="w-[90%] md:w-[320px] lg:w-[640px]" id="provider">
                    <option value="">Select a provider</option>
                </select>
            </div>
            <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%] mb-3">
                <label for="lang" class="md:w-[200px] inline-block">Select a language</label>
                <select class="w-[90%] md:w-[320px] lg:w-[640px]" id="lang">
                    <option value="">Select a language</option>
                </select>
            </div>
            <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%] mb-3">
                <label for="voice" class="md:w-[200px] inline-block">Select a voice</label>
                <select class="w-[90%] md:w-[320px] lg:w-[640px]" id="voice">
                    <option value="">Select a voice</option>
                </select>
            </div>
        </div>

        <div class="flex md:items-center justify-evenly max-md:flex-col max-md:px-[5%]">
            <label for="text" class="md:w-[200px] inline-block">Speak</label>
            <textarea id="text" class="w-[90%] lg:w-[640px] md:w-[320px] resize-none h-[120px]"
                      placeholder="Please input text to be said"></textarea>
        </div>

        <div class="min-h-[50px] max-md:px-[5%]">
            <div id="audio-player"
                 class="flex max-lg:flex-col-reverse justify-center gap-5 items-center my-8 p-0 hidden">
                <div>
                    <audio controls autoplay id="audio"></audio>
                </div>
                <div class="tooltip bottom" aria-label="Download File">
                    <button type="button" id="download" class="flex gap-x-2 items-center" title="Download File">
                        <span id="filename">azerty.wav</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="max-h-[20px] rounded">
                            <path d="M12 15V3"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="m7 10 5 5 5-5"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>


        <div class="mb-4 mt-8 flex justify-center gap-8">

            <button type="reset" class="secondary rounded-full px-8 py-4">
                Reset
            </button>


            <button disabled type="submit" class="primary rounded-full px-8 py-4" id="submitButton">
                Listen
            </button>
        </div>


    </form>


</main>
