import { useI18n } from '@/i18n';
import type { Provider } from '../types';
import { LogoMark, PlusIcon } from './icons';

export function Header({
    onNewChat,
    canReset,
    providers,
    provider,
    onProviderChange,
    busy,
}: {
    onNewChat: () => void;
    canReset: boolean;
    providers: Provider[];
    provider: string;
    onProviderChange: (name: string) => void;
    busy: boolean;
}) {
    const { t, locale, setLocale } = useI18n();

    return (
        <header className="border-b border-slate-200 bg-white">
            <div className="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
                <div className="flex items-center gap-3">
                    <LogoMark className="size-9 shrink-0" />
                    <div className="leading-tight">
                        <p className="font-semibold text-slate-900">
                            {t('app.name')}
                        </p>
                        <p className="hidden text-sm text-slate-500 sm:block">
                            {t('app.tagline')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {/* Offered only when the server has more than one
                        provider configured. A conversation cannot change
                        provider, so choosing another starts a new one. */}
                    {providers.length > 1 && (
                        <label title={t('app.modelHint')}>
                            <span className="sr-only">{t('app.model')}</span>
                            <select
                                value={provider}
                                disabled={busy}
                                onChange={(event) =>
                                    onProviderChange(event.target.value)
                                }
                                className="max-w-32 truncate rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {providers.map((item) => (
                                    <option key={item.name} value={item.name}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    <button
                        type="button"
                        onClick={onNewChat}
                        disabled={!canReset}
                        aria-label={t('app.newChat')}
                        className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <PlusIcon className="size-4" />
                        <span className="hidden sm:inline">
                            {t('app.newChat')}
                        </span>
                    </button>

                    {/* Labelled in the language it switches to, so it can be
                        found by someone who cannot read the current one. */}
                    <button
                        type="button"
                        onClick={() => setLocale(locale === 'ar' ? 'en' : 'ar')}
                        lang={locale === 'ar' ? 'en' : 'ar'}
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        {t('app.switchLanguage')}
                    </button>
                </div>
            </div>
        </header>
    );
}
