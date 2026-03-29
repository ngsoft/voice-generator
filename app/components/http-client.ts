import { getSessionAuthorization } from '@/components/session-authorization';
import type { Voice } from '@/types';
import type { BaseResponse } from '$packages/http';
import { HttpClient } from '$packages/http/service';

export interface SpeakRequest {
    text: string;
    lang: string;
    voice: string;
    rate?: number;
    pitch?: number;
    volume?: number;
    format?: 'mp3' | 'wav' | 'ogg' | string;
}

export interface SpeakResponse extends BaseResponse {
    provider?: string;
    voice?: Voice;
    seconds?: number;
    duration?: string;
    mime?: string;
    identifier?: string;
    url?: string;
    expires_at?: string;
}

export async function speakRequest(data: SpeakRequest): Promise<SpeakResponse> {
    try {
        const headers: Record<string, string> = {},
            authorization = await getSessionAuthorization();

        console.debug('authorization', authorization);
        if (authorization) {
            headers.Authorization = `Bearer ${authorization}`;
        }

        return await HttpClient.getInstance().request<SpeakResponse>('/api/speak', { data, headers });
    } catch (_) {
        return { success: false };
    }
}
