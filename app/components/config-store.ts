import type { Writable } from 'svelte/store';
import { environment } from '@';
import { type DataStore, LocalStore } from '$sdk';
import { debug } from '$sdk/environment';

export interface SavedConfig {
    voice: string;
    rate: number;
    pitch: number;
    volume: number;
    format: 'mp3' | 'wav' | 'ogg' | string;
}

export const localStore: DataStore = new LocalStore(localStorage, environment.app.id),
    sessionStore: DataStore = new LocalStore(sessionStorage, environment.app.id),
    configStore: Writable<SavedConfig | null> = localStore.writable('synthesis-player-form', null),
    savedConfigReminder: Writable<boolean> = localStore.writable('synthesis-player-form-reminder', false);

let cfginit = false;

configStore.subscribe((value) => {
    if (cfginit && value) {
        debug('updating configuration', value);
    }
    cfginit = true;
});
