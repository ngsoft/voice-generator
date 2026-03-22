import type { AppData, GlobalData } from './types';

const app: AppData = {
    id: import.meta.env.VITE_APP_ID ?? 'app',
    title: import.meta.env.VITE_APP_TITLE ?? '',
    // initialize app context there
    context: new Map<string, any>(),
};

export const environment: GlobalData = {
    production: import.meta.env.MODE === 'production',
    window,
    document,
    app,
    context: new Map<string, any>([
        ['window', window],
        ['document', document],
        ['app', app],
    ]),
};

export function debug(...values: any[]): void {
    if (!environment.production) {
        console.debug(...values);
    }
}
