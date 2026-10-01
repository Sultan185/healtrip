import {
    show,
    store as storeConversation,
} from '@/actions/App/Http/Controllers/Api/ConversationController';
import { store as storeMessage } from '@/actions/App/Http/Controllers/Api/MessageController';
import type { AgentEvent, ConversationSnapshot, Locale } from './types';

/** A request the server answered with an error we can show. */
export class ApiError extends Error {
    constructor(
        public readonly code: string,
        public readonly status: number,
    ) {
        super(code);
    }

    get retryable(): boolean {
        return this.status >= 500 || this.status === 429 || this.status === 409;
    }
}

/**
 * Laravel sets the CSRF token in the XSRF-TOKEN cookie and accepts it back in
 * this header. The API is same-origin and session-authenticated, so there is
 * no bearer token to keep in the browser.
 */
function headers(accept: string): HeadersInit {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return {
        Accept: accept,
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
    };
}

async function toApiError(response: Response): Promise<ApiError> {
    const fallback: Record<number, string> = {
        404: 'not_found',
        409: 'turn_in_progress',
        419: 'session_expired',
        422: 'invalid',
        429: 'rate_limited',
    };

    let code = fallback[response.status] ?? 'internal';

    try {
        const body: unknown = await response.json();

        if (
            body &&
            typeof body === 'object' &&
            'code' in body &&
            typeof body.code === 'string'
        ) {
            code = body.code;
        }
    } catch {
        // Not JSON; the status code is all we have.
    }

    return new ApiError(code, response.status);
}

/**
 * `provider` is the name of one of the providers the server listed; leave it
 * out to get the server's default.
 */
export async function createConversation(
    locale: Locale,
    provider?: string,
): Promise<ConversationSnapshot> {
    const response = await fetch(storeConversation.url(), {
        method: 'POST',
        headers: headers('application/json'),
        body: JSON.stringify({ locale, provider }),
    });

    if (!response.ok) {
        throw await toApiError(response);
    }

    return (await response.json()) as ConversationSnapshot;
}

export async function fetchConversation(
    id: string,
): Promise<ConversationSnapshot> {
    const response = await fetch(show.url(id), {
        headers: headers('application/json'),
    });

    if (!response.ok) {
        throw await toApiError(response);
    }

    return (await response.json()) as ConversationSnapshot;
}

/**
 * Send a patient message and call `onEvent` for each server-sent event as the
 * agent works. EventSource cannot POST, so the stream is read from fetch.
 */
export async function streamTurn(
    conversationId: string,
    payload: { text: string; locale: Locale },
    onEvent: (event: AgentEvent) => void,
): Promise<void> {
    const response = await fetch(storeMessage.url(conversationId), {
        method: 'POST',
        headers: headers('text/event-stream'),
        body: JSON.stringify(payload),
    });

    if (!response.ok || !response.body) {
        throw await toApiError(response);
    }

    const reader = response.body
        .pipeThrough(new TextDecoderStream())
        .getReader();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();

        if (done) {
            break;
        }

        buffer += value;

        // Events are separated by a blank line; keep any partial one buffered.
        const chunks = buffer.split('\n\n');
        buffer = chunks.pop() ?? '';

        for (const chunk of chunks) {
            const event = parseEvent(chunk);

            if (event) {
                onEvent(event);
            }
        }
    }
}

function parseEvent(chunk: string): AgentEvent | null {
    let type = '';
    let data = '';

    for (const line of chunk.split('\n')) {
        if (line.startsWith('event: ')) {
            type = line.slice(7);
        } else if (line.startsWith('data: ')) {
            data += line.slice(6);
        }
    }

    if (!type || !data) {
        return null;
    }

    try {
        return { type, data: JSON.parse(data) } as AgentEvent;
    } catch {
        return null;
    }
}
