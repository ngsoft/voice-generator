import type { Writable } from 'svelte/store';
import { LocalStore } from '$sdk';
import { environment } from '$sdk/environment';

type DarkModeValue = 'auto' | 'on' | 'off';

const store = new LocalStore(localStorage, environment.app.id),
    enabled: Writable<DarkModeValue | null> = store.writable('dark-mode-enabled', null),
    lightMode: MediaQueryList = globalThis.matchMedia('(prefers-color-scheme: light)'),
    darkModeSwitch: ZeptoCollection = $('#dark-mode-switch');

function toggleDarkMode(on: boolean) {
    environment.document.documentElement.classList.toggle('dark', on);
}

darkModeSwitch.on('change', () => {
    enabled.set(darkModeSwitch.prop('checked') ? 'on' : 'off');
});

enabled.subscribe((value: DarkModeValue | null) => {
    toggleDarkMode('auto' === value ? !lightMode.matches : 'on' === value);
});
