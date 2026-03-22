import type { HttpError } from './client';
import { Sdk } from './service';
import type { BaseResponse } from './types';

export async function __(text: string): Promise<string> {
    return await Sdk.getInstance()
        .request<BaseResponse & { text?: string }>('/api/translate', { data: { text } })
        .then((data) => data.text ?? text)
        .catch((error: HttpError) => {
            console.error('__', 'error:', error);
            return text;
        });
}
