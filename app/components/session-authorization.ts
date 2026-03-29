import { environment } from '$sdk/environment';

export async function getSessionAuthorization(): Promise<string | null> {
    try {
        const name = `${environment.app.id}_session_authorization`,
            cookie = await cookieStore.get(name);
        return cookie?.value ?? null;
    } catch (_) {}
    return null;
}
