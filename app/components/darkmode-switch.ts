import type { Writable } from 'svelte/store';
import { environment } from '@';
import { localStore } from './config-store';
import { app_get } from './data-loader';

type DarkModeValue = 'auto' | 'on' | 'off';

const store = localStore,
    doc = $(environment.document.documentElement),
    enabled: Writable<DarkModeValue | null> = store.writable('dark-mode-enabled', null),
    lightMode: MediaQueryList = globalThis.matchMedia('(prefers-color-scheme: light)'),
    darkModeSwitch = $('#dark-mode-switch'),
    darkModeToggle = $('.theme-selector'),
    { light, dark, skeleton } = environment.app.theme,
    forced_mode = app_get('force_color_mode') as null | DarkModeValue;

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
$(darkModeToggle).on('click', (event: Event) => {
    const btn = (event.target as HTMLElement).closest('button'),
        newValue = btn?.value as null | DarkModeValue;
    newValue && enabled.set(newValue);
});

enabled.subscribe((value: DarkModeValue | null) => {
    darkModeToggle.attr('data-value', value);
    toggleDarkMode('auto' === value ? !lightMode.matches : 'on' === value);
});

if (forced_mode) {
    darkModeSwitch.attr('disabled', true);
    darkModeToggle.addClass('pointer-events-none opacity-40');
    darkModeToggle.attr('data-value', forced_mode);
    toggleDarkMode('auto' === forced_mode ? !lightMode.matches : 'on' === forced_mode);
}
