import '@/components/darkmode-switch';
import { app_get } from '@/components/data-loader';
import { speakRequest } from '@/components/http-client';
import { showModal } from '@/components/modal';
import type { Langs, Provider, Voice } from '@/types';
import { finder } from '$sdk';

finder.one(`form#synthesis-player-form`, async (form: HTMLFormElement) => {
    const providers: Provider | null = app_get('providers'),
        voices: { [lang_name: string]: { [provider_name: string]: Voice[] } } | null = app_get('voices'),
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
            voice_options: HTMLOptionElement[] = [],
            voice_groups: HTMLOptGroupElement[] = [],
            voice_map = new Map<string, Voice & { provider: string }>(),
            voice_group_map = new Map<HTMLOptionElement, HTMLOptGroupElement>();

        // build voices options
        for (const lang_name in voices) {
            const group = $(`<optgroup label="${lang_name}"></optgroup>`).get(0) as HTMLOptGroupElement;
            group.setAttribute('data-providers', JSON.stringify(Object.keys(providers)));
            voice_groups.push(group);
            controls.voice.appendChild(group);
            for (const provider_name in voices[lang_name]) {
                for (const voice of voices[lang_name][provider_name]) {
                    const name = `${voice.lang}|${voice.name}`;
                    const option = $(
                        `<option value="${name}">${voice.friendlyName} (${provider_name}: ${voice.lang})</option>`
                    ).get(0) as HTMLOptionElement;
                    voice_map.set(name, { ...voice, provider: provider_name });
                    voice_options.push(option);
                    voice_group_map.set(option, group);
                    group.appendChild(option);
                }
            }
        }

        function filterVoices(reset: boolean = false) {
            const provider = controls.provider.value,
                lang = controls.lang.value;

            console.debug('filterVoices', provider, lang);
            for (const group of voice_groups) {
                const providers = JSON.parse(group.getAttribute('data-providers') ?? '[]') as string[];
                if (!reset && 'all' !== provider && !providers.includes(provider)) {
                    group.remove();
                } else if (!reset && 'all' !== lang && group.getAttribute('label') !== lang) {
                    group.remove();
                } else if (!group.parentElement) {
                    controls.voice.appendChild(group);
                }
            }

            for (const option of voice_options) {
                if (!reset && 'all' !== provider && voice_map.get(option.value)?.provider !== provider) {
                    option.remove();
                } else if (!option.parentElement) {
                    voice_group_map.get(option)?.appendChild(option);
                }
            }
            controls.voice.selectedIndex = 0;
        }

        $(form)
            .on('submit', async (event: Event) => {
                event.preventDefault();
                if (!form.checkValidity()) {
                    await showModal('Some fields are not valid.');
                }
                $(controls.player).addClass('opacity-0 invisible');

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
                        $(controls.download).attr({ href: resp.url, download: filename });
                        $(controls.filename).text(filename);
                        $(controls.audio).attr('src', resp.url);
                        $(controls.player).removeClass('opacity-0 invisible');
                        try {
                            await controls.audio.play();
                            return;
                        } catch (_) {}
                    }
                    await showModal('An error occurred.');
                }
            })
            .on('reset', (_event: Event) => {
                [controls.pitch, controls.volume, controls.rate].forEach((target) => $(target).next().html('1.0'));
                filterVoices(true);
                $(controls.player).addClass('opacity-0 invisible');
            })
            .on('change', (event: Event) => {
                const target = (event.target as HTMLElement).closest('input,textarea,select') as
                    | HTMLInputElement
                    | HTMLTextAreaElement
                    | HTMLSelectElement;

                if (target) {
                    const name = target.getAttribute('name');
                    switch (name) {
                        case 'pitch':
                        case 'volume':
                        case 'rate':
                            $(target).next().html(parseFloat(target.value).toFixed(1));
                            break;
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
