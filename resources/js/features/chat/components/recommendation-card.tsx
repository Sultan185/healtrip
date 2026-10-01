import type { ReactNode } from 'react';
import { useI18n } from '@/i18n';
import { isMessageKey } from '@/i18n/messages';
import type { MessageKey } from '@/i18n/messages';
import { cn } from '@/lib/utils';
import type { Doctor, Hospital, Recommendation, Urgency } from '../types';
import { PhoneIcon, ShieldCheckIcon, StarIcon } from './icons';

const URGENCY_STYLE: Record<Urgency, { card: string; badge: string }> = {
    emergency: {
        card: 'border-red-300',
        badge: 'bg-red-600 text-white',
    },
    urgent: {
        card: 'border-amber-300',
        badge: 'bg-amber-500 text-white',
    },
    routine: {
        card: 'border-slate-200',
        badge: 'bg-teal-700 text-white',
    },
};

function Tag({ children }: { children: ReactNode }) {
    return (
        <span className="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
            {children}
        </span>
    );
}

/** Marks a block as database-sourced, with the record ID for auditing. */
function Verified({ id }: { id: number }) {
    const { t } = useI18n();

    return (
        <p className="flex items-center gap-1 text-xs text-teal-800">
            <ShieldCheckIcon className="size-3.5 shrink-0" />
            {t('card.verified')}
            <span className="text-slate-400">· {t('card.id', { id })}</span>
        </p>
    );
}

function languageNames(
    codes: string[],
    t: (key: MessageKey) => string,
): string {
    return codes
        .map((code) => {
            const key = `language.${code}`;

            return isMessageKey(key) ? t(key) : code;
        })
        .join(' · ');
}

function DoctorCard({ doctor }: { doctor: Doctor }) {
    const { t, pick, formatDate } = useI18n();

    return (
        <li className="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3.5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-slate-900">
                        {t('doctor.prefix')} {pick(doctor.name)}
                    </p>
                    <p className="text-sm text-slate-600">
                        {pick(doctor.specialty)} · {pick(doctor.focus)}
                    </p>
                </div>
                <p className="flex shrink-0 items-center gap-1 text-sm font-medium text-slate-700">
                    <StarIcon className="size-4 text-amber-500" />
                    {doctor.rating.toFixed(1)}
                </p>
            </div>

            <p className="text-sm text-slate-600">
                {pick(doctor.hospital.name)} — {pick(doctor.hospital.city)}
                {t('punct.comma')}
                {pick(doctor.hospital.country)}
            </p>

            <div className="flex flex-wrap gap-1.5">
                <Tag>
                    {t('doctor.fee', { fee: doctor.consultation_fee_usd })}
                </Tag>
                <Tag>
                    {t('doctor.experience', { years: doctor.years_experience })}
                </Tag>
                <Tag>
                    {t('doctor.nextAvailable', {
                        date: formatDate(doctor.next_available_on),
                    })}
                </Tag>
                {doctor.offers_second_opinion && (
                    <Tag>{t('doctor.secondOpinion')}</Tag>
                )}
                {doctor.offers_telemedicine && (
                    <Tag>{t('doctor.telemedicine')}</Tag>
                )}
            </div>

            <p className="text-xs text-slate-500">
                {t('doctor.speaks')}: {languageNames(doctor.languages, t)}
            </p>

            <Verified id={doctor.id} />
        </li>
    );
}

/** Phone numbers are always left-to-right, even inside Arabic text. */
function PhoneLink({
    label,
    number,
    urgent = false,
}: {
    label: string;
    number: string;
    urgent?: boolean;
}) {
    return (
        <a
            href={`tel:${number.replace(/\s+/g, '')}`}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700',
                urgent
                    ? 'border-red-300 bg-red-50 text-red-800 hover:bg-red-100'
                    : 'border-slate-300 text-slate-700 hover:bg-slate-50',
            )}
        >
            <PhoneIcon className="size-4 shrink-0" />
            <span>{label}</span>
            <bdi dir="ltr" className="tabular-nums">
                {number}
            </bdi>
        </a>
    );
}

function HospitalCard({ hospital }: { hospital: Hospital }) {
    const { t, pick } = useI18n();

    return (
        <li className="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3.5">
            <div>
                <p className="font-semibold text-slate-900">
                    {pick(hospital.name)}
                </p>
                <p className="text-sm text-slate-600">
                    {pick(hospital.city)}
                    {t('punct.comma')}
                    {pick(hospital.country)} ·{' '}
                    <span dir="ltr">{hospital.address}</span>
                </p>
            </div>

            <div className="flex flex-wrap gap-1.5">
                <Tag>
                    {hospital.has_emergency
                        ? t('hospital.emergency')
                        : t('hospital.noEmergency')}
                </Tag>
                {hospital.accreditation && <Tag>{hospital.accreditation}</Tag>}
                {hospital.international_patient_desk && (
                    <Tag>{t('hospital.patientDesk')}</Tag>
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                {hospital.emergency_phone && (
                    <PhoneLink
                        label={t('hospital.emergencyPhone')}
                        number={hospital.emergency_phone}
                        urgent
                    />
                )}
                <PhoneLink
                    label={t('hospital.phone')}
                    number={hospital.phone}
                />
            </div>

            <Verified id={hospital.id} />
        </li>
    );
}

/**
 * The agent's recommendation. The model chose the urgency, the care path and
 * wrote the summary; every provider detail below it was loaded from the
 * database by ID on the server.
 */
export function RecommendationCard({
    recommendation,
}: {
    recommendation: Recommendation;
}) {
    const { t, pick } = useI18n();
    const style = URGENCY_STYLE[recommendation.urgency];

    return (
        <section
            aria-label={t('card.title')}
            className={cn(
                'flex flex-col gap-4 rounded-2xl border bg-slate-50 p-4',
                style.card,
            )}
        >
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'rounded-md px-2 py-0.5 text-xs font-semibold tracking-wide uppercase',
                            style.badge,
                        )}
                    >
                        {t(`urgency.${recommendation.urgency}`)}
                    </span>
                    <span className="text-xs text-slate-500">
                        {t(`urgency.${recommendation.urgency}.hint`)}
                    </span>
                </div>

                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                    {t('card.title')}
                </p>
                <p className="text-lg font-semibold text-slate-900">
                    {t(`carePath.${recommendation.care_path}`)}
                    {recommendation.specialty && (
                        <span className="font-normal text-slate-600">
                            {' '}
                            · {pick(recommendation.specialty)}
                        </span>
                    )}
                </p>
                <p dir="auto" className="leading-relaxed text-slate-700">
                    {recommendation.summary}
                </p>
            </div>

            {recommendation.hospitals.length > 0 && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-slate-700">
                        {t('card.hospitals')}
                    </p>
                    <ul className="flex flex-col gap-2">
                        {recommendation.hospitals.map((hospital) => (
                            <HospitalCard
                                key={hospital.id}
                                hospital={hospital}
                            />
                        ))}
                    </ul>
                </div>
            )}

            {recommendation.doctors.length > 0 && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm font-medium text-slate-700">
                        {t('card.doctors')}
                    </p>
                    <ul className="flex flex-col gap-2">
                        {recommendation.doctors.map((doctor) => (
                            <DoctorCard key={doctor.id} doctor={doctor} />
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
