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
                    class="absolute inset-y-0 inset-s-0 m-auto size-6 rounded-full bg-gray-500 transition-[inset-inline-start] peer-checked:inset-s-6 peer-checked:*:scale-0 dark:bg-gray-400">
                    <span
                        class="absolute inset-0 m-auto size-4 rounded-full bg-gray-200 transition-transform dark:bg-gray-700"></span>
                </span>
            </span>
            <span><?= __('Dark Mode') ?></span>
        </label>
    </div>
</header>
<main>
    <div class="card p-6 rounded-lg max-md:w-full min-w-[70%]">
        <h3 class="h3 mb-8"><?= __("Voices") ?></h3>
        <form autocomplete="off" method="post"
              class="mx-auto grid grid-cols-1 gap-6 sm:grid-cols-2 select-none w-full" id="synthesis-player-form">

            <div class="md:col-span-2">
                <div class="select-floating">
                    <select class="select select-lg rounded-full" id="provider" name="provider">
                        <option value="all"><?= __("All") ?></option>
                        <?php foreach (var_get('providers', $vite_data ?? [], []) as $value => $label):
                            echo renderTag('option', ['value' => $value], $label);
                        endforeach; ?>
                    </select>
                    <label for="provider"
                           class="select-floating-label text-sm font-medium"><?= __('Provider') ?></label>
                </div>
            </div>

            <div>
                <div class="select-floating">
                    <select id="voice" name="voice" class="select select-lg rounded-full" required>
                        <option value=""><?= __('Select a voice') ?></option>
                    </select>
                    <label for="voice" class="select-floating-label text-sm font-medium"><?= __('Voice') ?></label>
                </div>
            </div>

            <div>
                <div class="select-floating">

                    <select name="lang" id="lang" class="select select-lg rounded-full">
                        <option value="all" selected><?= __('All') ?></option>
                        <?php foreach (var_get('langs', $vite_data ?? [], []) as $label => $langs):
                            $options = [];
                            foreach ($langs as $lang):
                                $options[] = renderTag('option', ['value' => $lang], $lang);
                            endforeach;
                            echo renderTag('optgroup', compact('label'), implode("\n", $options));
                        endforeach; ?>
                    </select><label for="lang"
                                    class="select-floating-label text-sm font-medium"><?= __('Languages') ?></label>
                </div>
            </div>

            <div>
                <div class="select-floating">
                    <select id="format" name="format" class="select select-lg rounded-full">
                        <option value="mp3">MP3</option>
                        <option value="wav">PCM (wav)</option>
                        <option value="ogg">OGG Vorbis</option>
                    </select>
                    <label for="format"
                           class="select-floating-label text-sm font-medium"><?= __('Format') ?></label>
                </div>
            </div>

            <div>
                <div class="input-floating">
                    <div class="input input-lg rounded-full">
                        <div class="flex gap-x-4 items-center justify-between w-full">
                            <span>0.1</span>
                            <input class="range range-primary range-lg max-w-50"
                                   id="rate"
                                   name="rate"
                                   type="range"
                                   value="1.0"
                                   max="10.0"
                                   min="0.1"
                                   step="0.1">
                            <label
                                class="max-w-30 flex">
                                <input
                                    class="p-0 bg-transparent border-0 focus:ring-0 text-center min-w-16"
                                    id="rate-value"
                                    type="text"
                                    value="1.0"
                                    max="10.0"
                                    min="0.1"
                                    step="0.1">
                                <span class="my-auto flex gap-2">
                                    <button type="button" value="-0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0">
                                      <span class="icon-[lucide--minus] size-3.5 shrink-0"></span>
                                    </button>
                                    <button type="button" value="0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0">
                                        <span class="icon-[lucide--plus] size-3.5 shrink-0"></span>
                                    </button>
                                </span>
                            </label>
                            <span>10.0</span>
                        </div>
                    </div>
                    <label for="rate"
                           class="input-floating-label  text-sm font-medium"><?= __('Speed') ?></label>
                </div>
            </div>


            <div>
                <div class="input-floating">
                    <div class="input input-lg rounded-full">
                        <div class="flex gap-x-4 items-center justify-between w-full">
                            <span>0.0</span>
                            <input class="range range-primary range-lg max-w-50"
                                   type="range"
                                   id="pitch"
                                   name="pitch"
                                   value="1.0"
                                   min="0.0"
                                   max="2.0"
                                   step="0.1">
                            <label
                                class="max-w-30 flex">
                                <input
                                    class="p-0 bg-transparent border-0 focus:ring-0 text-center min-w-16"
                                    id="pitch-value"
                                    type="text"
                                    value="1.0"
                                    min="0.0"
                                    max="2.0"
                                    step="0.1">
                                <span class="my-auto flex gap-2">
                                    <button type="button" value="-0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0">
                                      <span class="icon-[lucide--minus] size-3.5 shrink-0"></span>
                                    </button>
                                    <button type="button" value="0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0">
                                        <span class="icon-[lucide--plus] size-3.5 shrink-0"></span>
                                    </button>
                              </span>
                            </label>
                            <span>2.0</span>
                        </div>
                    </div>
                    <label for="pitch"
                           class="input-floating-label  text-sm font-medium"><?= __('Pitch') ?></label>
                </div>

            </div>
            <div>
                <div class="input-floating">
                    <div class="input input-lg rounded-full">
                        <div class="flex gap-x-4 items-center justify-between w-full">
                            <span>0.0</span>
                            <input id="volume"
                                   name="volume"
                                   class="range range-primary range-lg max-w-50"
                                   type="range"
                                   value="1.0"
                                   min="0.0"
                                   max="2.0"
                                   step="0.1">
                            <label
                                class="max-w-30 flex">
                                <input
                                    id="volume-value"
                                    class="p-0 bg-transparent border-0 focus:ring-0 text-center min-w-16"
                                    type="text"
                                    value="1.0"
                                    min="0.0"
                                    max="2.0"
                                    step="0.1"
                                    aria-roledescription="Number field">
                                <span class="my-auto flex gap-2">
                                    <button type="button" value="-0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0"
                                            aria-label="Decrement button"
                                            data-input-number-decrement>
                                        <span class="icon-[lucide--minus] size-3.5 shrink-0"></span>
                                    </button>
                                    <button type="button" value="0.1"
                                            class="btn btn-primary btn-soft size-5.5 min-h-0 rounded-full p-0"
                                            aria-label="Increment button"
                                            data-input-number-increment>
                                        <span class="icon-[lucide--plus] size-3.5 shrink-0"></span>
                                    </button>
                                </span>
                            </label>
                            <span>2.0</span>
                        </div>
                    </div>
                    <label for="volume"
                           class="input-floating-label  text-sm font-medium"><?= __('Volume') ?></label>
                </div>

            </div>
            <div class="md:col-span-2 mt-3">
                <div class="textarea-floating">

                    <textarea
                        required
                        placeholder="<?= __('Please input text to be said') ?>"
                        class="textarea textarea-lg rounded-container resize-none"
                        id="text" name="text" rows="4"></textarea>
                    <label for="text" class="textarea-floating-label text-sm font-medium"><?= __('Message') ?></label>
                </div>
            </div>

            <div class="md:col-span-2">
                <div id="audio-player"
                     class="flex max-lg:flex-col-reverse justify-center gap-5 items-center p-0 pt-4 transition-all duration-500 overflow-hidden opacity-0 invisible h-0">
                    <div>
                        <audio controls autoplay id="audio"></audio>
                    </div>

                    <a href="#" target="_blank" download="" id="download"
                       class="btn btn-outline border-dashed waves waves-light flex gap-x-2 items-center py-2 px-4"
                       title="Download File">
                        <span id="filename"></span>
                        <span class="icon-[lucide--download] size-5 shrink-0"></span>
                    </a>

                </div>
            </div>

            <div class="md:col-span-2 flex justify-between items-center gap-6 mt-6 w-full">
                <button
                    class="btn btn-lg btn-secondary waves waves-light rounded-lg p-4 w-[25%]"
                    type="reset">
                    <?= __('Reset') ?>
                </button>

                <button
                    class="btn btn-lg btn-primary waves waves-light rounded-lg p-4 w-full"
                    type="submit">
                    <?= __('Listen') ?>
                </button>
            </div>
        </form>
    </div>
</main>


