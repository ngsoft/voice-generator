import type { HttpError } from './client';
import { HttpClient } from './service';
import type { BaseResponse } from './types';

export async function __(text: string, lang?: string | null): Promise<string> {
    lang ??= null;
    return await HttpClient.getInstance()
        .request<BaseResponse & { text?: string }>('/api/translate', { data: { text, lang } })
        .then((data) => data.text ?? text)
        .catch((error: HttpError) => {
            console.error('__', 'error:', error);
            return text;
        });
}
