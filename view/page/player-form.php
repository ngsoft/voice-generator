<?php
extend('vite');
title($title = __('Speech Synthesis Player'));
?>
<header class="flex items-center p-4 border-b border-surface-200-800 bg-surface-50-950/75 backdrop-blur-lg select-none">
    <div class="container mx-auto flex items-center justify-evenly">
        <span class="logo ps-4 capitalize h5"><?= trim($title ?? env_get('APP_TITLE', 'My App', false)); ?></span>
        <label class="ms-auto select-none flex items-center gap-x-2 cursor-pointer">
            <span class="ms-auto relative block h-8 w-12 [-webkit-tap-highlight-color:transparent]">
                <input type="checkbox" id="dark-mode-switch" class="peer sr-only">
                <span class="absolute inset-0 m-auto h-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                <span
                    class="absolute inset-y-0 start-0 m-auto size-6 rounded-full bg-gray-500 transition-[inset-inline-start] peer-checked:start-6 peer-checked:*:scale-0 dark:bg-gray-400">
                    <span
                        class="absolute inset-0 m-auto size-4 rounded-full bg-gray-200 transition-transform dark:bg-gray-700"></span>
                </span>
            </span>
            <span><?= __('Dark Mode') ?></span>
        </label>
    </div>
</header>
<main>
    <div class="card preset-tonal-surface p-6 rounded-lg max-md:w-full min-w-[70%]">
        <h3 class="h3 mb-4"><?= __("Voices") ?></h3>
        <form autocomplete="off" method="post"
              class="mx-auto grid grid-cols-1 gap-4 sm:grid-cols-2 select-none w-full" id="synthesis-player-form">
            <span class="flex items-center md:col-span-2 w-full">
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
                <span class="shrink-0 px-4 text-gray-900 dark:text-white"><?= __('Provider') ?></span>
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
            </span>
            <div>

                <label class="label relative">
                    <span class="label-text text-sm font-medium"><?= __('Provider') ?></span>
                    <select class="select py-2 px-4" id="provider" name="provider">
                        <option value="all"><?= __("All") ?></option>
                        <?php foreach (var_get('providers', $vite_data ?? [], []) as $value => $label):
                            echo renderTag('option', ['value' => $value], $label);
                        endforeach; ?>
                    </select>
                </label>
            </div>

            <div>
                <label class="label relative">
                    <span class="label-text text-sm font-medium"><?= __('Languages') ?></span>
                    <select name="lang" id="lang" class="select py-2 px-4">
                        <option value="all" selected><?= __('All') ?></option>
                        <?php foreach (var_get('langs', $vite_data ?? [], []) as $label => $langs):
                            $options = [];
                            foreach ($langs as $lang):
                                $options[] = renderTag('option', ['value' => $lang], $lang);
                            endforeach;
                            echo renderTag('optgroup', compact('label'), implode("\n", $options));
                        endforeach; ?>
                    </select>
                </label>
            </div>


            <span class="flex items-center md:col-span-2">
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
                <span class="shrink-0 px-4 text-gray-900 dark:text-white"><?= __('Lang & voice') ?></span>
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
            </span>

            <div>
                <label class="label relative">
                    <span class="label-text text-sm font-medium"><?= __('Voice') ?></span>
                    <select id="voice" name="voice" class="select py-2 px-4" required>
                        <option value=""><?= __('Select a voice') ?></option>
                    </select>
                </label>
            </div>

            <div>
                <label class="label">
                    <span class="label-text text-sm font-medium"><?= __('Format') ?></span>
                    <select id="format" name="format" class="select py-2 px-4">
                        <option value="mp3">MP3</option>
                        <option value="wav">PCM 16bits</option>
                        <option value="ogg">OGG Vorbis 16bits</option>
                    </select>
                </label>
            </div>

            <div class="md:col-span-2 mt-3">
                <label class="label relative">
                    <span class="label-text text-sm font-medium"><?= __('Message') ?></span>
                    <textarea
                        required
                        placeholder="<?= __('Please input text to be said') ?>"
                        class="textarea rounded-container resize-none "
                        id="text" name="text" rows="4"></textarea>
                </label>
            </div>

            <span class="flex items-center md:col-span-2 my-2">
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
                <span class="shrink-0 px-4 text-gray-900 dark:text-white"><?= __('Settings') ?></span>
                <span class="h-px flex-1 bg-gray-300 dark:bg-gray-600"></span>
            </span>

            <div class="md:col-span-2 flex justify-evenly flex-col md:flex-row">
                <div>
                    <label class="label">
                        <span class="label-text text-sm font-medium"><?= __('Speed') ?></span>
                        <span class="flex gap-x-2 items-center ">
                            <input id="rate" name="rate" class="input" type="range" step="0.1" value="1.0" max="10"
                                   min="0.1">
                            <span class="text-sm/none font-medium">1.0</span>
                        </span>

                    </label>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text text-sm font-medium"><?= __('Pitch') ?></span>
                        <span class="flex gap-x-2 items-center">
                            <input class="input" type="range" id="pitch" name="pitch" step="0.1" value="1.0" min="0.0"
                                   max="2.0">
                            <span class="text-sm/none font-medium">1.0</span>
                        </span>

                    </label>
                </div>
                <div>
                    <label class="label">
                        <span class="label-text text-sm font-medium"><?= __('Volume') ?></span>
                        <span class="flex gap-x-2 items-center ">
                           <input id="volume" name="volume" class="input" type="range" step="0.1" value="1.0" max="2.0"
                                  min="0.0">
                            <span class="text-sm/none font-medium">1.0</span>
                        </span>
                    </label>
                </div>
            </div>


            <div class="min-h-[50px] max-md:px-[5%] md:col-span-2 my-4">
                <div id="audio-player"
                     class="flex max-lg:flex-col-reverse justify-center gap-5 items-center p-0 pt-4 transition-all duration-500 opacity-0 invisible h-0">
                    <div>
                        <audio controls autoplay id="audio"></audio>
                    </div>

                    <button type="button" id="download"
                            class="flex gap-x-2 items-center btn preset-outlined-surface-500 rounded-lg border py-2 px-4"
                            title="Download File">
                        <span id="filename">azerty.wav</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="max-h-[20px] rounded">
                            <path d="M12 15V3"></path>
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="m7 10 5 5 5-5"></path>
                        </svg>
                    </button>

                </div>
            </div>

            <div class="md:col-span-2  flex justify-center items-center gap-6 w-full">
                <button
                    class=" btn btn-lg preset-filled-secondary-500 rounded-lg p-4 w-[25%]"
                    type="reset">
                    <?= __('Reset') ?>
                </button>

                <button
                    class="btn btn-lg preset-filled-primary-500 rounded-lg p-4 w-full"
                    type="submit">
                    <?= __('Listen') ?>
                </button>
            </div>
        </form>
    </div>
</main>


