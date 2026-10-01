import { useI18n } from '@/i18n';
import { isMessageKey } from '@/i18n/messages';
import type { Provider, Turn } from '../types';
import { AgentTrace } from './agent-trace';
import { AlertIcon } from './icons';
import { RecommendationCard } from './recommendation-card';
import { SafetyBanner } from './safety-banner';

/**
 * Model and patient text is rendered as plain text nodes (React escapes it)
 * with `dir="auto"`, so an Arabic message reads right-to-left inside an
 * English interface and vice versa. No HTML or markdown is interpreted.
 */
function Bubble({ text }: { text: string }) {
    return (
        <p
            dir="auto"
            className="rounded-2xl rounded-ss-md border border-slate-200 bg-white px-4 py-3 leading-relaxed whitespace-pre-wrap text-slate-800"
        >
            {text}
        </p>
    );
}

function Working({ turn }: { turn: Turn }) {
    const { t } = useI18n();
    const running = turn.tools.find((tool) => tool.running);
    const key = `status.${running?.name}`;

    return (
        <p
            role="status"
            className="flex items-center gap-2 px-1 text-sm text-slate-500"
        >
            <span className="flex gap-1" aria-hidden="true">
                <span className="size-1.5 animate-bounce rounded-full bg-teal-600 [animation-delay:-0.3s]" />
                <span className="size-1.5 animate-bounce rounded-full bg-teal-600 [animation-delay:-0.15s]" />
                <span className="size-1.5 animate-bounce rounded-full bg-teal-600" />
            </span>
            {isMessageKey(key) ? t(key) : t('status.thinking')}
        </p>
    );
}

function ErrorNotice({
    turn,
    onRetry,
    onResync,
}: {
    turn: Turn;
    onRetry: () => void;
    onResync: () => void;
}) {
    const { t } = useI18n();

    if (!turn.error) {
        return null;
    }

    const key = `error.${turn.error.code}`;
    const connectionLost = turn.error.code === 'connection_lost';

    return (
        <div
            role="alert"
            className="flex items-start gap-3 rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-700"
        >
            <AlertIcon className="mt-0.5 size-4 shrink-0 text-amber-600" />
            <div className="flex flex-col items-start gap-2">
                <p>{isMessageKey(key) ? t(key) : t('error.internal')}</p>
                {turn.error.retryable && (
                    <button
                        type="button"
                        onClick={connectionLost ? onResync : onRetry}
                        className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        {connectionLost ? t('error.resync') : t('error.retry')}
                    </button>
                )}
            </div>
        </div>
    );
}

export function TurnView({
    turn,
    provider,
    isLast,
    onRetry,
    onResync,
}: {
    turn: Turn;
    provider?: Provider;
    isLast: boolean;
    onRetry: () => void;
    onResync: () => void;
}) {
    const streaming = turn.status === 'streaming';

    // The card sits where it arrived: after anything the assistant said while
    // working, and before its closing remarks.
    const closing = streaming ? [] : turn.texts.slice(-1);
    const before = streaming ? turn.texts : turn.texts.slice(0, -1);

    return (
        <article className="flex flex-col gap-3">
            <p
                dir="auto"
                className="max-w-[85%] self-end rounded-2xl rounded-ee-md bg-teal-700 px-4 py-3 leading-relaxed whitespace-pre-wrap text-white"
            >
                {turn.userText}
            </p>

            <div
                className="flex max-w-[95%] flex-col gap-3 self-start sm:max-w-[88%]"
                aria-live="polite"
            >
                {turn.safety && <SafetyBanner safety={turn.safety} />}

                {before.map((text, index) => (
                    <Bubble key={index} text={text} />
                ))}

                {turn.recommendation && (
                    <RecommendationCard recommendation={turn.recommendation} />
                )}

                {closing.map((text) => (
                    <Bubble key="closing" text={text} />
                ))}

                {streaming && <Working turn={turn} />}

                {/* Only the latest turn can be retried: an older one has
                    already been followed by more conversation. */}
                {isLast && (
                    <ErrorNotice
                        turn={turn}
                        onRetry={onRetry}
                        onResync={onResync}
                    />
                )}

                {!streaming && <AgentTrace turn={turn} provider={provider} />}
            </div>
        </article>
    );
}
