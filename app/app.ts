import { initializeRange } from '@/components/range-slider';

import('@/libs');
import '@/components/darkmode-switch';
import type { HSSelect, ISingleOption } from 'preline/non-auto';
import { getSelect } from '@/components/advanced-select';
import { configStore, type SavedConfig, savedConfigReminder } from '@/components/config-store';
import { app_get } from '@/components/data-loader';
import { speakRequest } from '@/components/http-client';
import { displayModal } from '@/components/modal';
import type { Langs, Provider, Voice } from '@/types';
import { finder } from '$sdk';
import { debug } from '$sdk/environment';

finder.one(`form#synthesis-player-form`, async (form: HTMLFormElement) => {
    const providers: Provider | null = app_get('providers'),
        voices: { [lang_name: string]: Voice[] } | null = app_get('voices'),
        langs: Langs | null = app_get('langs');
    let locked = false,
        loaded = false;
    if (voices && langs && providers) {
        const controls = {
                form,
                provider: form.querySelector('#provider') as HTMLSelectElement,
                format: form.querySelector('#format') as HTMLSelectElement,
                lang: form.querySelector('#lang') as HTMLSelectElement,
                voice: form.querySelector('#voice') as HTMLSelectElement,
                text: form.querySelector('#text') as HTMLTextAreaElement,
                rate: form.querySelector('#rate') as HTMLInputElement,
                pitch: form.querySelector('#pitch') as HTMLInputElement,
                volume: form.querySelector('#volume') as HTMLInputElement,
                download: form.querySelector('#download') as HTMLAnchorElement,
                submitButton: form.querySelector('#submitButton') as HTMLButtonElement,
                filename: form.querySelector('#filename') as HTMLSpanElement,
                audio: form.querySelector('#audio') as HTMLAudioElement,
                player: form.querySelector('#audio-player') as HTMLDivElement,
                loader: form.querySelector('.loading') as HTMLSpanElement,
            },
            voice_select: HSSelect = getSelect(controls.voice),
            voice_options: ISingleOption[] = [],
            voice_map = new Map<string, Voice>(),
            lang_select: HSSelect = getSelect(controls.lang),
            format_select: HSSelect = getSelect(controls.format),
            provider_select: HSSelect = getSelect(controls.provider),
            rate_range = initializeRange(controls.rate),
            pitch_range = initializeRange(controls.pitch),
            volume_range = initializeRange(controls.volume);

        // build voices options select
        for (const lang_name in voices) {
            for (const voice of voices[lang_name]) {
                const opt: ISingleOption = {
                    title: `${voice.friendlyName} (${voice.provider}: ${voice.lang})`,
                    val: `${voice.lang}|${voice.name}`,
                    options: {
                        apiFields: {
                            provider: voice.provider,
                            lang: voice.lang,
                        },
                    },
                };
                voice_options.push(opt);
                const option = $(`<option value="${opt.val}">${opt.title}</option>`).get(0) as HTMLOptionElement;
                voice_map.set(opt.val, voice);
                controls.voice.appendChild(option);
                voice_select.addOption({ ...opt });
            }
        }

        function loader(on: boolean = true) {
            $(controls.loader).toggleClass('hidden', !(locked = on));
        }

        function filterVoices(reset: boolean = false) {
            const provider = controls.provider.value,
                lang = controls.lang.value;

            voice_select.close();

            // remove all options
            voice_select.removeOption(voice_options.map((opt) => opt.val));
            // add options based on filters
            for (const opt of voice_options) {
                if (!reset && 'all' !== provider && opt.options?.apiFields?.provider !== provider) {
                    continue;
                }
                if (!reset && 'all' !== lang && opt.options?.apiFields?.lang !== lang) {
                    continue;
                }
                voice_select.addOption({ ...opt });
            }
            voice_select.setValue('');
            controls.voice.selectedIndex = 0;
        }

        $(form)
            .on('submit', async (event: Event) => {
                event.preventDefault();
                if (locked) {
                    await displayModal({
                        message: 'One request at a time',
                        title: '<span class="text-error">Error</span>',
                    });
                    return;
                }
                if (!form.checkValidity()) {
                    await displayModal({
                        message: 'Some fields are not valid.',
                        title: '<span class="text-error">Error</span>',
                    });
                    return;
                }
                $(controls.player).addClass('opacity-0 invisible h-0');

                const voice_name = controls.voice.value,
                    voice = voice_map.get(voice_name);
                if (voice) {
                    loader();
                    $(controls.loader).toggleClass('hidden', false);
                    const format = controls.format.value,
                        resp = await speakRequest({
                            text: controls.text.value,
                            lang: voice.lang,
                            voice: voice.name,
                            rate: rate_range.value,
                            pitch: pitch_range.value,
                            volume: volume_range.value,
                            format,
                        });
                    loader(false);
                    if (resp.success && resp.url) {
                        // save config
                        configStore.set({
                            voice: voice_name,
                            rate: rate_range.value,
                            pitch: pitch_range.value,
                            volume: volume_range.value,
                            format,
                        });

                        if (!loaded) {
                            const { confirmed } = await displayModal({
                                title: 'Information',
                                message: 'Your configuration has been saved',
                            });
                            if (confirmed) {
                                loaded = true;
                                savedConfigReminder.set(true);
                            }
                        }

                        controls.audio.pause();
                        const filename = `${resp.url.split('/').slice(-1)[0]}.${format}`;
                        $(controls.download).attr({ href: resp.url, download: filename });
                        $(controls.filename).text(filename);
                        $(controls.audio).attr('src', resp.url);
                        $(controls.player).removeClass('opacity-0 invisible h-0');
                        try {
                            await controls.audio.play();
                            return;
                        } catch (_) {}
                    }
                    await displayModal({
                        message: 'An error occurred.',
                        title: '<span class="text-error">Error</span>',
                    });
                }
            })
            .on('reset', (_event: Event) => {
                filterVoices(true);
                provider_select.setValue('all');
                format_select.setValue('mp3');
                lang_select.setValue('all');
                $(controls.player).addClass('opacity-0 invisible h-0');
                debug('erasing saved configuration');
                configStore.set(null);
            })
            .on('change', (event: Event) => {
                const target = (event.target as HTMLElement).closest('input,textarea,select') as
                    | HTMLInputElement
                    | HTMLTextAreaElement
                    | HTMLSelectElement;

                if (target) {
                    const name = target.getAttribute('name');
                    switch (name) {
                        case 'provider':
                        case 'lang':
                            filterVoices();
                            break;
                        case 'text':
                            target.value = target.value.trim();
                            break;
                    }
                }
            });

        // load stored config

        configStore.subscribe((value: SavedConfig | null) => {
            if (value) {
                if (voice_map.has(value.voice)) {
                    debug('loading saved configuration', value);
                    voice_select.setValue(value.voice);
                    format_select.setValue(value.format);
                    rate_range.value = value.rate;
                    pitch_range.value = value.pitch;
                    volume_range.value = value.volume;
                    loaded = true;
                }
            }
        })();

        savedConfigReminder.subscribe((value) => {
            if (value) {
                loaded = true;
            }
        })();
    }
});
