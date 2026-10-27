import { environment } from '@';

function readDocumentCookie(name: string): string | null {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

export async function getSessionAuthorization(): Promise<string | null> {
    const name = `${environment.app.id}_session_authorization`;

    try {
        if (typeof globalThis.cookieStore !== 'undefined') {
            const cookie = await globalThis.cookieStore.get(name);

            if (cookie?.value) {
                return cookie.value;
            }
        }
    } catch (_) {
        // Cookie Store API unavailable / insecure context — fall back below
    }

    try {
        return readDocumentCookie(name);
    } catch (_) {}

    return null;
}
