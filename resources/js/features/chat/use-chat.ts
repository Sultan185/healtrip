import { useRef, useState } from 'react';
import {
    ApiError,
    createConversation,
    fetchConversation,
    streamTurn,
} from './api';
import type {
    AgentEvent,
    ConversationSnapshot,
    Locale,
    Provider,
    Turn,
    TurnError,
} from './types';

function fromSnapshot(snapshot: ConversationSnapshot): Turn[] {
    return snapshot.turns.map((turn) => ({
        key: `saved-${turn.turn}`,
        userText: turn.user_text ?? '',
        safety: turn.safety,
        texts: turn.texts,
        tools: turn.tools.map((tool) => ({ ...tool, running: false })),
        recommendation: turn.recommendation,
        guard: turn.guard,
        error: null,
        status: 'done',
    }));
}

/** Fold one server-sent event into the turn it belongs to. */
function applyEvent(turn: Turn, event: AgentEvent): Turn {
    switch (event.type) {
        case 'safety':
            return { ...turn, safety: event.data };

        case 'tool.started':
            return {
                ...turn,
                tools: [
                    ...turn.tools,
                    {
                        ...event.data,
                        running: true,
                        is_error: false,
                        error: null,
                        stats: null,
                        duration_ms: null,
                    },
                ],
            };

        case 'tool.finished':
            return {
                ...turn,
                tools: turn.tools.map((tool) =>
                    tool.id === event.data.id
                        ? { ...tool, ...event.data, running: false }
                        : tool,
                ),
            };

        case 'recommendation':
            return { ...turn, recommendation: event.data };

        case 'guard':
            return { ...turn, guard: event.data.action };

        case 'message':
            return { ...turn, texts: [...turn.texts, event.data.text] };

        case 'error':
            return { ...turn, error: event.data };

        case 'done':
            return { ...turn, status: 'done' };

        default:
            return turn;
    }
}

function toTurnError(error: unknown): TurnError {
    if (error instanceof ApiError) {
        return { code: error.code, retryable: error.retryable };
    }

    // fetch rejected or the stream broke: the network, not the server.
    return { code: 'connection_lost', retryable: true };
}

export function useChat(
    initial: ConversationSnapshot | null,
    locale: Locale,
    providers: Provider[],
    defaultProvider: string,
) {
    const [turns, setTurns] = useState<Turn[]>(() =>
        initial ? fromSnapshot(initial) : [],
    );
    const [busy, setBusy] = useState(false);
    const [provider, setProvider] = useState(
        initial?.provider.name ?? defaultProvider,
    );
    const conversationId = useRef<string | null>(initial?.id ?? null);

    // A conversation runs on one provider for its whole life, so the choice
    // is made when it is created. Only a provider the server listed is ever
    // sent; anything else falls back to the server's default.
    const startConversation = async (wanted: string) => {
        const created = await createConversation(
            locale,
            providers.some((item) => item.name === wanted) ? wanted : undefined,
        );

        conversationId.current = created.id;
        setProvider(created.provider.name);

        return created.id;
    };

    const updateTurn = (key: string, update: (turn: Turn) => Turn) =>
        setTurns((current) =>
            current.map((turn) => (turn.key === key ? update(turn) : turn)),
        );

    const send = async (text: string) => {
        if (busy || text.trim() === '') {
            return;
        }

        const key = crypto.randomUUID();

        setBusy(true);
        setTurns((current) => [
            ...current,
            {
                key,
                userText: text.trim(),
                safety: null,
                texts: [],
                tools: [],
                recommendation: null,
                guard: null,
                error: null,
                status: 'streaming',
            },
        ]);

        try {
            const id =
                conversationId.current ?? (await startConversation(provider));

            await streamTurn(id, { text: text.trim(), locale }, (event) =>
                updateTurn(key, (turn) => applyEvent(turn, event)),
            );

            // A stream that ends without `done` was cut off mid-turn.
            updateTurn(key, (turn) =>
                turn.status === 'done'
                    ? turn
                    : {
                          ...turn,
                          status: 'done',
                          error: turn.error ?? {
                              code: 'connection_lost',
                              retryable: true,
                          },
                      },
            );
        } catch (error) {
            updateTurn(key, (turn) => ({
                ...turn,
                status: 'done',
                error: toTurnError(error),
            }));
        } finally {
            setBusy(false);
        }
    };

    /**
     * Reload the conversation from the server. The server finishes a turn
     * even if the browser disconnects, so after a dropped connection the
     * stored conversation is the truth.
     */
    const resync = async () => {
        if (!conversationId.current || busy) {
            return;
        }

        setBusy(true);

        try {
            setTurns(
                fromSnapshot(await fetchConversation(conversationId.current)),
            );
        } catch {
            // Keep what is on screen; the error banner is still showing.
        } finally {
            setBusy(false);
        }
    };

    const reset = async (nextProvider: string = provider) => {
        if (busy) {
            return;
        }

        setTurns([]);
        setProvider(nextProvider);
        conversationId.current = null;

        // Create the next conversation now so a page reload does not bring
        // the old one back. If this fails, `send` will create it instead.
        try {
            await startConversation(nextProvider);
        } catch {
            // Ignored on purpose; see above.
        }
    };

    return { turns, busy, provider, send, resync, reset };
}
