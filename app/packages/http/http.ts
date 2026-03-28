import type { HttpError } from './client';
import { HttpClient } from './service';
import type { BaseResponse } from './types';

const translation_cache = new Map<string, string>();

export async function __(text: string, lang?: string | null): Promise<string> {
    lang ??= null;
    const key = `:${lang ?? ''}:${text}`;
    if (translation_cache.has(key)) {
        return translation_cache.get(key) as string;
    }

    if (!text.length) {
        return text;
    }

    return await HttpClient.getInstance()
        .request<BaseResponse>('/api/translate', { data: { text, lang } })
        .then((data) => {
            const result = data.message ?? text;
            translation_cache.set(key, result);
            return result;
        })
        .catch((error: HttpError) => {
            console.error('__', 'error:', error);
            return text;
        });
}
