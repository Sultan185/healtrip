import type { ReactNode } from 'react';
import { useI18n } from '@/i18n';
import { isMessageKey, safetyCategories } from '@/i18n/messages';
import { cn } from '@/lib/utils';
import type { Provider, ToolCall, Turn } from '../types';
import { CheckIcon, ChevronIcon, SearchIcon, XIcon } from './icons';

function Step({
    tone,
    title,
    detail,
    children,
}: {
    tone: 'ok' | 'warn' | 'error' | 'running';
    title: string;
    detail?: string;
    children?: ReactNode;
}) {
    const marker = {
        ok: 'bg-teal-100 text-teal-800',
        warn: 'bg-amber-100 text-amber-800',
        error: 'bg-red-100 text-red-700',
        running: 'bg-slate-100 text-slate-500',
    }[tone];

    return (
        <li className="flex gap-2.5">
            <span
                className={cn(
                    'mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full',
                    marker,
                )}
            >
                {tone === 'error' ? (
                    <XIcon className="size-3" />
                ) : tone === 'running' ? (
                    <SearchIcon className="size-3 animate-pulse" />
                ) : (
                    <CheckIcon className="size-3" />
                )}
            </span>
            <div className="flex min-w-0 flex-col gap-1">
                <p className="text-sm text-slate-800">
                    <span className="font-medium">{title}</span>
                    {detail && (
                        <span className="text-slate-500"> — {detail}</span>
                    )}
                </p>
                {children}
            </div>
        </li>
    );
}

/** The arguments the model passed, minus the filters it left empty. */
function Arguments({ input }: { input: ToolCall['input'] }) {
    const { t } = useI18n();
    const used = Object.entries(input).filter(
        ([key, value]) =>
            key !== 'summary' &&
            value !== null &&
            value !== false &&
            !(Array.isArray(value) && value.length === 0),
    );

    return (
        <p
            dir="ltr"
            className="font-mono text-xs break-words text-slate-500 rtl:text-right"
        >
            {used.length === 0
                ? t('trace.filters.none')
                : used
                      .map(([key, value]) => `${key}=${JSON.stringify(value)}`)
                      .join('  ')}
        </p>
    );
}

function ToolStep({ tool }: { tool: ToolCall }) {
    const { t } = useI18n();

    const titleKey = `trace.tool.${tool.name}`;
    const title = isMessageKey(titleKey) ? t(titleKey) : tool.name;

    let detail: string;

    if (tool.running) {
        detail = t('trace.result.running');
    } else if (tool.is_error) {
        const errorKey = `trace.error.${tool.error}`;

        detail = isMessageKey(errorKey)
            ? t(errorKey)
            : t('trace.error.other', { code: tool.error ?? 'error' });
    } else if (tool.stats) {
        detail =
            tool.stats.total === 0
                ? t('trace.result.none')
                : t('trace.result.matches', tool.stats);
    } else {
        detail = t('trace.result.displayed');
    }

    if (tool.duration_ms !== null) {
        detail += ` · ${tool.duration_ms} ms`;
    }

    return (
        <Step
            tone={tool.running ? 'running' : tool.is_error ? 'error' : 'ok'}
            title={title}
            detail={detail}
        >
            <Arguments input={tool.input} />
        </Step>
    );
}

/**
 * The audit trail for one answer: what the safety screen found, which tools
 * the model called with which arguments, what came back, and whether the
 * output check intervened. It is collapsed by default; it is here so that a
 * recommendation can be traced back to the directory queries behind it.
 */
export function AgentTrace({
    turn,
    provider,
}: {
    turn: Turn;
    provider?: Provider;
}) {
    const { t, locale } = useI18n();
    const steps = 1 + turn.tools.length + (turn.guard ? 1 : 0);

    return (
        <details className="group rounded-xl border border-slate-200 bg-white text-sm">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-2 rounded-xl px-3 py-2 text-slate-600 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 [&::-webkit-details-marker]:hidden">
                <span>
                    {t('trace.title')}
                    <span className="text-slate-400">
                        {' '}
                        · {t('trace.steps', { count: steps })}
                    </span>
                </span>
                <ChevronIcon className="size-4 shrink-0 transition-transform group-open:rotate-180" />
            </summary>

            <ol className="flex flex-col gap-3 border-t border-slate-200 px-3 py-3">
                <Step
                    tone={turn.safety ? 'warn' : 'ok'}
                    title={t('trace.safety')}
                    detail={
                        turn.safety
                            ? t('trace.safety.flagged', {
                                  categories: turn.safety.categories
                                      .map(
                                          (category) =>
                                              safetyCategories[category]?.[
                                                  locale
                                              ] ?? category,
                                      )
                                      .join(locale === 'ar' ? '، ' : ', '),
                              })
                            : t('trace.safety.clear')
                    }
                />

                {turn.tools.map((tool) => (
                    <ToolStep key={tool.id} tool={tool} />
                ))}

                {turn.guard && (
                    <Step
                        tone={turn.guard === 'blocked' ? 'error' : 'warn'}
                        title={t(`trace.guard.${turn.guard}`)}
                    />
                )}
            </ol>

            {provider && (
                <p className="border-t border-slate-200 px-3 py-2 text-xs text-slate-500">
                    {t('trace.model')}: {provider.label}
                    <span dir="ltr" className="font-mono">
                        {' '}
                        · {provider.model}
                    </span>
                </p>
            )}
        </details>
    );
}
