import { getContext } from 'svelte';
import { environment } from '../environment';
import { easyTryCatch } from './easy-try-catch';

const bases = new Map<Document, string>();

function fixPath(path: string): string {
    return path.replace(/\/+$/, '');
}

export function basePath(doc?: Document): string {
    doc ??=
        easyTryCatch<Document>(() => getContext('document') as Document)[0]
        ?? (environment.context.get('document') as Document);

    if (!bases.has(doc)) {
        let base = '/';
        const elem = doc.querySelector(`base[href]`);
        if (elem) {
            base = elem.getAttribute('href') ?? '/';
        }
        bases.set(doc, fixPath(base));
    }

    return bases.get(doc) as string;
}

export function asset(name: string, base?: string): string {
    return `${fixPath(base ?? basePath())}/assets/${name}`;
}
