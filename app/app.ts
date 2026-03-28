import '@/components/darkmode-switch';
import { app_get } from '@/components/data-loader';
import { showModal } from '@/components/modal';
import type { Langs, Provider, Voice } from '@/types';
import { finder } from '$sdk';
import { debug } from '$sdk/environment';

function getProvider(voice: Voice): string {
    return voice.voiceUri.split('://')[0];
}

finder.one(`form#synthesis-player-form`, (form: HTMLFormElement) => {
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
                download: form.querySelector('#download') as HTMLButtonElement,
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
                    const url = voice.voiceUri;
                    const data = await fetch(url, {
                        method: 'POST',
                        body: JSON.stringify({
                            text: controls.text.value,
                            lang: voice.lang,
                            voice: voice.name,
                            rate: parseFloat(controls.rate.value),
                            pitch: parseFloat(controls.pitch.value),
                            volume: parseFloat(controls.volume.value),
                            format: controls.format.value,
                        }),
                        headers: { Accept: 'application/json', 'content-type': 'application/json' },
                    }).then((response) => response.json());

                    if (data.url) {
                        $(controls.player).removeClass('opacity-0 invisible');
                        controls.audio.setAttribute('src', data.url);
                        controls.filename.innerHTML = `${data.url.split('/').slice(-1)[0]}.${controls.format.value}`;

                        try {
                            await controls.audio.play();
                        } catch (_) {
                            await showModal('An error occurred.');
                        }
                        return;
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

        $(controls.download).on('click', () => {
            const url = controls.audio.getAttribute('src');
            const link = $(
                `<a href="${url}" download="${controls.download.innerText}" class="visually-hidden">Download file</a>`
            ).get(0);
            document.body.appendChild(link);
            link.click();
            requestAnimationFrame(() => link.remove());
        });
    }
});

const form = document.getElementById('player-form') as HTMLFormElement | null;

if (form) {
    const _base_url: string | null = app_get('base_url'),
        providers: Provider | null = app_get('providers'),
        voices: { [lang: string]: Voice[] } | null = app_get('voices'),
        langs: Langs | null = app_get('langs');
    debug(voices, langs, providers);
    if (voices) {
        const controls = {
            form,
            provider: document.getElementById('provider') as HTMLSelectElement,
            lang: document.getElementById('lang') as HTMLSelectElement,
            voice: document.getElementById('voice') as HTMLSelectElement,
            text: document.getElementById('text') as HTMLTextAreaElement,
            download: document.getElementById('download') as HTMLButtonElement,
            submitButton: document.getElementById('submitButton') as HTMLButtonElement,
            filename: document.getElementById('filename') as HTMLSpanElement,
            audio: document.getElementById('audio') as HTMLAudioElement,
            player: document.getElementById('audio-player') as HTMLDivElement,
        };

        for (const name in providers) {
            $(controls.provider).append($(`<option value="${name}">${providers[name]}</option>`));
        }

        for (const prefix in langs) {
            const group = $(`<optgroup label="${prefix}"></optgroup>`);
            $(controls.lang).append(group);
            for (const lang of langs[prefix]) {
                group.append($(`<option value="${lang}">${lang}</option>`));
            }
        }

        for (const lang in voices) {
            const group = $(`<optgroup label="${lang}">`);
            $(controls.voice).append(group);
            for (const voice of voices[lang]) {
                group.append(
                    $(
                        `<option value="${getProvider(voice)}|${voice.lang}|${voice.name}">${voice.friendlyName}</option>`
                    )
                );
            }
        }
    }
}
