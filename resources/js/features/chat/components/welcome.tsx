import { useI18n } from '@/i18n';
import type { MessageKey } from '@/i18n/messages';
import { ShieldCheckIcon } from './icons';

const EXAMPLES: MessageKey[] = [
    'welcome.example.1',
    'welcome.example.2',
    'welcome.example.3',
    'welcome.example.4',
];

export function Welcome({ onPick }: { onPick: (text: string) => void }) {
    const { t } = useI18n();

    return (
        <div className="mx-auto flex max-w-xl flex-col gap-6 py-8 sm:py-14">
            <div className="flex flex-col gap-3">
                <h1 className="text-2xl font-semibold text-slate-900 sm:text-3xl">
                    {t('welcome.title')}
                </h1>
                <p className="leading-relaxed text-slate-600">
                    {t('welcome.body')}
                </p>
                <p className="flex items-start gap-2 text-sm text-teal-800">
                    <ShieldCheckIcon className="mt-0.5 size-4 shrink-0" />
                    {t('welcome.grounding')}
                </p>
            </div>

            <div className="flex flex-col gap-2">
                <p className="text-sm font-medium text-slate-500">
                    {t('welcome.try')}
                </p>
                <ul className="flex flex-col gap-2">
                    {EXAMPLES.map((key) => (
                        <li key={key}>
                            <button
                                type="button"
                                onClick={() => onPick(t(key))}
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-start text-sm leading-relaxed text-slate-700 hover:border-teal-600 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                {t(key)}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
