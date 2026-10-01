import { createContext, use, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import type { Locale, Localized } from '@/features/chat/types';
import { messages } from './messages';
import type { MessageKey } from './messages';

type I18n = {
    locale: Locale;
    dir: 'ltr' | 'rtl';
    setLocale: (locale: Locale) => void;
    /** Translate an interface string, filling `{placeholders}`. */
    t: (key: MessageKey, values?: Record<string, string | number>) => string;
    /** Pick the current language from a bilingual database field. */
    pick: (value: Localized) => string;
    formatDate: (isoDate: string) => string;
};

const STORAGE_KEY = 'healtrip.locale';

const I18nContext = createContext<I18n | null>(null);

/** The visitor's explicit choice, else the conversation's, else the browser's. */
function initialLocale(preferred?: Locale): Locale {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        if (stored === 'en' || stored === 'ar') {
            return stored;
        }
    } catch {
        // Storage can be unavailable (private mode); fall through.
    }

    if (preferred) {
        return preferred;
    }

    return navigator.language.toLowerCase().startsWith('ar') ? 'ar' : 'en';
}

export function I18nProvider({
    preferred,
    children,
}: {
    preferred?: Locale;
    children: ReactNode;
}) {
    const [locale, setLocaleState] = useState<Locale>(() =>
        initialLocale(preferred),
    );
    const dir = locale === 'ar' ? 'rtl' : 'ltr';

    // The whole document flips, so layout written with logical properties
    // (start/end rather than left/right) mirrors without per-component work.
    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = dir;
    }, [locale, dir]);

    const setLocale = (next: Locale) => {
        setLocaleState(next);

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Not persisted; the choice still applies to this visit.
        }
    };

    const t: I18n['t'] = (key, values) => {
        let text: string = messages[locale][key];

        for (const [name, value] of Object.entries(values ?? {})) {
            text = text.replace(`{${name}}`, String(value));
        }

        return text;
    };

    // Latin digits in both languages: fees, dates and phone numbers stay
    // readable next to the mixed-script provider data.
    const dateFormat = new Intl.DateTimeFormat(
        locale === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB',
        { day: 'numeric', month: 'short' },
    );

    const value: I18n = {
        locale,
        dir,
        setLocale,
        t,
        pick: (localized) => localized[locale],
        formatDate: (isoDate) => dateFormat.format(new Date(isoDate)),
    };

    return <I18nContext value={value}>{children}</I18nContext>;
}

export function useI18n(): I18n {
    const context = use(I18nContext);

    if (!context) {
        throw new Error('useI18n must be used inside <I18nProvider>.');
    }

    return context;
}
