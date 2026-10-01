import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { useI18n } from '@/i18n';
import { cn } from '@/lib/utils';
import { SendIcon } from './icons';

export function Composer({
    onSend,
    disabled,
    maxLength,
}: {
    onSend: (text: string) => void;
    disabled: boolean;
    maxLength: number;
}) {
    const { t } = useI18n();
    const [text, setText] = useState('');

    const canSend = !disabled && text.trim() !== '';
    const nearLimit = text.length > maxLength * 0.9;

    const submit = () => {
        if (!canSend) {
            return;
        }

        onSend(text);
        setText('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        // isComposing: Enter is confirming an IME candidate, not sending.
        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            submit();
        }
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="flex flex-col gap-1.5"
        >
            <div className="flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-2 shadow-sm focus-within:border-teal-700 focus-within:ring-2 focus-within:ring-teal-700/20">
                <label htmlFor="message" className="sr-only">
                    {t('composer.label')}
                </label>
                {/* dir="auto": the text aligns to the language being typed,
                    whichever language the interface is in. */}
                <textarea
                    id="message"
                    dir="auto"
                    rows={1}
                    value={text}
                    maxLength={maxLength}
                    onChange={(event) => setText(event.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder={t('composer.placeholder')}
                    className="field-sizing-content max-h-40 min-h-10 flex-1 resize-none bg-transparent px-2 py-2 text-slate-900 placeholder:text-slate-400 focus:outline-none"
                />
                <button
                    type="submit"
                    disabled={!canSend}
                    aria-label={t('composer.send')}
                    className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                >
                    <SendIcon className="size-5 rtl:-scale-x-100" />
                </button>
            </div>

            <div className="flex justify-between gap-3 px-1 text-xs text-slate-500">
                <span className="hidden sm:inline">{t('composer.hint')}</span>
                <span
                    className={cn(
                        'ms-auto tabular-nums',
                        nearLimit ? 'text-amber-700' : 'invisible',
                    )}
                >
                    {text.length} / {maxLength}
                </span>
            </div>
        </form>
    );
}
