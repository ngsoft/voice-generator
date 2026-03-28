const elem = document.getElementById('app-data') as HTMLScriptElement | null,
    data = new Map<string, any>();

export function app_get(name: string, defaultValue: any = null): any {
    return data.get(name) ?? defaultValue;
}

if (elem) {
    try {
        const parsed = JSON.parse(elem.innerHTML);
        if (typeof parsed === 'object' && null !== parsed) {
            for (const key in parsed) {
                data.set(key, parsed[key]);
            }
        }
    } catch (_) {}
}
