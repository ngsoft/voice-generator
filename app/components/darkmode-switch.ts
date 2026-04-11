import type { Writable } from 'svelte/store';
import { environment } from '@';
import { LocalStore } from '$sdk';

type DarkModeValue = 'auto' | 'on' | 'off';

const store = new LocalStore(localStorage, environment.app.id),
    doc = $(environment.document.documentElement),
    enabled: Writable<DarkModeValue | null> = store.writable('dark-mode-enabled', null),
    lightMode: MediaQueryList = globalThis.matchMedia('(prefers-color-scheme: light)'),
    darkModeSwitch = $('#dark-mode-switch'),
    { light, dark, skeleton } = environment.app.theme;

function toggleDarkMode(on: boolean) {
    doc.toggleClass('dark', on).attr({
        'data-skeleton': skeleton,
        'data-theme': on ? dark : light,
    });
    darkModeSwitch.prop('checked', on);
}

darkModeSwitch.on('change', () => {
    enabled.set(darkModeSwitch.prop('checked') ? 'on' : 'off');
});

enabled.subscribe((value: DarkModeValue | null) => {
    toggleDarkMode('auto' === value ? !lightMode.matches : 'on' === value);
});
