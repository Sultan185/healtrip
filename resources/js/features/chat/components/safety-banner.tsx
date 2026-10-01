import { useI18n } from '@/i18n';
import type { Safety } from '../types';
import { AlertIcon } from './icons';

/**
 * Shown when the server's deterministic safety screen flags a message. It
 * arrives before the model has produced anything and does not depend on what
 * the model goes on to say.
 */
export function SafetyBanner({ safety }: { safety: Safety }) {
    const { t } = useI18n();

    if (safety.level === 'emergency') {
        return (
            <div
                role="alert"
                className="flex gap-3 rounded-xl border border-red-300 bg-red-50 p-4 text-red-900"
            >
                <AlertIcon className="mt-0.5 size-5 shrink-0 text-red-600" />
                <div className="flex flex-col gap-1.5">
                    <p className="font-semibold">
                        {t('safety.emergency.title')}
                    </p>
                    <p className="text-sm leading-relaxed">
                        {t('safety.emergency.body')}
                    </p>
                    <p className="text-sm font-medium">
                        {t('safety.emergency.numbers')}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            role="status"
            className="flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900"
        >
            <AlertIcon className="mt-0.5 size-5 shrink-0 text-amber-600" />
            <div className="flex flex-col gap-1">
                <p className="font-semibold">{t('safety.urgent.title')}</p>
                <p className="text-sm leading-relaxed">
                    {t('safety.urgent.body')}
                </p>
            </div>
        </div>
    );
}
