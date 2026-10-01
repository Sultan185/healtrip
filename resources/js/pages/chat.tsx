import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Composer } from '@/features/chat/components/composer';
import { Header } from '@/features/chat/components/header';
import { TurnView } from '@/features/chat/components/turn-view';
import { Welcome } from '@/features/chat/components/welcome';
import type { ConversationSnapshot, Provider } from '@/features/chat/types';
import { useChat } from '@/features/chat/use-chat';
import { I18nProvider, useI18n } from '@/i18n';

type Props = {
    /** The session's current conversation, so a reload resumes it. */
    conversation: ConversationSnapshot | null;
    /** The model providers the server has configured (may be empty). */
    providers: Provider[];
    defaultProvider: string;
    limits: { maxMessageLength: number };
};

function ChatScreen({
    conversation,
    providers,
    defaultProvider,
    limits,
}: Props) {
    const { t, locale } = useI18n();
    const { turns, busy, provider, send, resync, reset } = useChat(
        conversation,
        locale,
        providers,
        defaultProvider,
    );
    const answeredBy =
        providers.find((item) => item.name === provider) ??
        conversation?.provider;
    const bottom = useRef<HTMLDivElement>(null);

    // Follow the conversation as the turn streams in.
    const last = turns.at(-1);
    const progress = `${turns.length}:${last?.texts.length}:${last?.tools.length}:${last?.status}`;

    useEffect(() => {
        bottom.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [progress]);

    return (
        <div className="flex h-dvh flex-col bg-slate-50 text-slate-900">
            <Head title={t('app.tagline')} />
            <Header
                onNewChat={() => reset()}
                canReset={!busy && turns.length > 0}
                providers={providers}
                provider={provider}
                onProviderChange={(name) => reset(name)}
                busy={busy}
            />

            <main className="flex-1 overflow-y-auto">
                <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-6">
                    {turns.length === 0 ? (
                        <Welcome onPick={send} />
                    ) : (
                        turns.map((turn, index) => (
                            <TurnView
                                key={turn.key}
                                turn={turn}
                                provider={answeredBy}
                                isLast={index === turns.length - 1}
                                onRetry={() => send(turn.userText)}
                                onResync={resync}
                            />
                        ))
                    )}
                    <div ref={bottom} />
                </div>
            </main>

            <footer className="border-t border-slate-200 bg-slate-50">
                <div className="mx-auto flex max-w-3xl flex-col gap-2 px-4 pt-3 pb-4">
                    <Composer
                        onSend={send}
                        disabled={busy}
                        maxLength={limits.maxMessageLength}
                    />
                    <p className="text-center text-xs leading-relaxed text-slate-500">
                        {t('app.disclaimer')} {t('app.prototype')}
                    </p>
                </div>
            </footer>
        </div>
    );
}

export default function Chat(props: Props) {
    return (
        <I18nProvider preferred={props.conversation?.locale}>
            <ChatScreen {...props} />
        </I18nProvider>
    );
}
