import type { SVGProps } from 'react';

function Icon({ children, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            {children}
        </svg>
    );
}

export const SendIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M5 12h14M13 6l6 6-6 6" />
    </Icon>
);

export const PlusIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M12 5v14M5 12h14" />
    </Icon>
);

export const AlertIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M12 3 2.5 19.5h19L12 3Z" />
        <path d="M12 10v4M12 17h.01" />
    </Icon>
);

export const ShieldCheckIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M12 3 4.5 6v5.5c0 4.5 3.1 8 7.5 9.5 4.4-1.5 7.5-5 7.5-9.5V6L12 3Z" />
        <path d="m9 12 2 2 4-4" />
    </Icon>
);

export const ChevronIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="m6 9 6 6 6-6" />
    </Icon>
);

export const PhoneIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z" />
    </Icon>
);

export const SearchIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <circle cx="11" cy="11" r="6.5" />
        <path d="m20 20-4.2-4.2" />
    </Icon>
);

export const CheckIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="m5 12.5 4.5 4.5L19 7.5" />
    </Icon>
);

export const XIcon = (props: SVGProps<SVGSVGElement>) => (
    <Icon {...props}>
        <path d="M6 6l12 12M18 6 6 18" />
    </Icon>
);

export const StarIcon = (props: SVGProps<SVGSVGElement>) => (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" {...props}>
        <path d="m12 3 2.7 5.6 6.1.8-4.5 4.3 1.1 6.1L12 16.9l-5.4 2.9 1.1-6.1L3.2 9.4l6.1-.8L12 3Z" />
    </svg>
);

/** The HealTrip mark: a route that ends in a medical cross. */
export const LogoMark = (props: SVGProps<SVGSVGElement>) => (
    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true" {...props}>
        <rect width="32" height="32" rx="9" className="fill-teal-700" />
        <path
            d="M8 22c3-6 6-2 8-7"
            className="stroke-teal-200"
            strokeWidth="2"
            strokeLinecap="round"
            strokeDasharray="1 3.5"
        />
        <path
            d="M20.5 7.5v9M16 12h9"
            className="stroke-white"
            strokeWidth="2.75"
            strokeLinecap="round"
        />
    </svg>
);
