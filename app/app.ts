import '@/components/darkmode-switch';
import { app_get } from '@/components/data-loader';
import type { Langs, Provider, Voice } from '@/types';
import { debug } from '$sdk/environment';

function getProvider(voice: Voice): string {
    return voice.voiceUri.split('://')[0];
}

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
