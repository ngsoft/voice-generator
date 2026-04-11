import {initializeRange} from '@/components/range-slider';

import('@/libs');
import '@/components/darkmode-switch';
import type {HSSelect, ISingleOption} from 'preline/non-auto';
import {getSelect} from '@/components/advanced-select';
import {app_get} from '@/components/data-loader';
import {speakRequest} from '@/components/http-client';
import {showModal} from '@/components/modal';
import type {Langs, Provider, Voice} from '@/types';
import {finder} from '$sdk';

finder.one(`form#synthesis-player-form`, async (form: HTMLFormElement) => {
    const providers: Provider | null = app_get('providers'),
        voices: { [lang_name: string]: Voice[] } | null = app_get('voices'),
        langs: Langs | null = app_get('langs');

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
            },
            voice_select: HSSelect = getSelect(controls.voice),
            voice_options: ISingleOption[] = [],
            voice_map = new Map<string, Voice>(),
            // lang autocomplete
            lang_select = getSelect(controls.lang),
            format_select = getSelect(controls.format),
            provider_select = getSelect(controls.provider);

        initializeRange(controls.rate);
        initializeRange(controls.pitch);
        initializeRange(controls.volume);

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
                voice_select.addOption({...opt});
            }
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
                voice_select.addOption({...opt});
            }
            voice_select.setValue('');
            controls.voice.selectedIndex = 0;
        }

        $(form)
            .on('submit', async (event: Event) => {
                event.preventDefault();
                if (!form.checkValidity()) {
                    await showModal('Some fields are not valid.');
                    return;
                }
                $(controls.player).addClass('opacity-0 invisible h-0');

                const voice_name = controls.voice.value,
                    voice = voice_map.get(voice_name);
                if (voice) {
                    const format = controls.format.value,
                        resp = await speakRequest({
                            text: controls.text.value,
                            lang: voice.lang,
                            voice: voice.name,
                            rate: parseFloat(controls.rate.value),
                            pitch: parseFloat(controls.pitch.value),
                            volume: parseFloat(controls.volume.value),
                            format,
                        });
                    if (resp.success && resp.url) {
                        controls.audio.pause();
                        const filename = `${resp.url.split('/').slice(-1)[0]}.${format}`;
                        $(controls.download).attr({href: resp.url, download: filename});
                        $(controls.filename).text(filename);
                        $(controls.audio).attr('src', resp.url);
                        $(controls.player).removeClass('opacity-0 invisible h-0');
                        try {
                            await controls.audio.play();
                            return;
                        } catch (_) {
                        }
                    }
                    await showModal('An error occurred.');
                }
            })
            .on('reset', (_event: Event) => {
                filterVoices(true);
                provider_select.setValue('all');
                format_select.setValue('mp3');
                lang_select.setValue('all');
                $(controls.player).addClass('opacity-0 invisible h-0');
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
    }
});
