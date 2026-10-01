/*
 * Shapes shared with the backend. Provider objects mirror the API resources
 * (app/Http/Resources): every field on a doctor or hospital card is read from
 * the database, never written by the model.
 */

export type Locale = 'en' | 'ar';

export type Localized = Record<Locale, string>;

export type Urgency = 'routine' | 'urgent' | 'emergency';

export type CarePath =
    | 'emergency_care'
    | 'specialist_visit'
    | 'second_opinion'
    | 'primary_care'
    | 'telemedicine';

export type Specialty = Localized & { slug: string };

export type Country = Localized & { code: string };

export type Doctor = {
    id: number;
    name: Localized;
    specialty: Specialty;
    focus: Localized;
    years_experience: number;
    languages: string[];
    consultation_fee_usd: number;
    offers_second_opinion: boolean;
    offers_telemedicine: boolean;
    rating: number;
    next_available_on: string;
    hospital: {
        id: number;
        name: Localized;
        city: Localized;
        country: Country;
    };
};

export type Hospital = {
    id: number;
    name: Localized;
    city: Localized;
    country: Country;
    address: string;
    phone: string;
    has_emergency: boolean;
    emergency_phone: string | null;
    accreditation: string | null;
    languages: string[];
    international_patient_desk: boolean;
    specialties?: Specialty[];
};

export type Recommendation = {
    id: number;
    urgency: Urgency;
    care_path: CarePath;
    specialty: Specialty | null;
    summary: string;
    doctors: Doctor[];
    hospitals: Hospital[];
};

/** A configured model provider, as the server offers it. Never a key. */
export type Provider = {
    name: string;
    label: string;
    model: string;
};

export type Safety = {
    level: Urgency;
    categories: string[];
};

export type ToolName =
    | 'search_doctors'
    | 'search_hospitals'
    | 'present_recommendation';

export type ToolCall = {
    id: string;
    name: ToolName | (string & {});
    input: Record<string, unknown>;
    running: boolean;
    is_error: boolean;
    error: string | null;
    stats: { returned: number; total: number } | null;
    duration_ms: number | null;
};

export type TurnError = {
    code: string;
    retryable: boolean;
};

/** One patient message and everything the assistant did in response. */
export type Turn = {
    key: string;
    userText: string;
    safety: Safety | null;
    texts: string[];
    tools: ToolCall[];
    recommendation: Recommendation | null;
    /** The output guard withheld a draft: 'redraft' (retried) or 'blocked'. */
    guard: 'redraft' | 'blocked' | null;
    error: TurnError | null;
    status: 'streaming' | 'done';
};

/** A conversation as GET /api/conversations/{id} returns it. */
export type ConversationSnapshot = {
    id: string;
    locale: Locale;
    provider: Provider;
    urgency: Urgency | null;
    turns: {
        turn: number;
        user_text: string | null;
        safety: Safety | null;
        texts: string[];
        tools: Omit<ToolCall, 'running'>[];
        recommendation: Recommendation | null;
        guard: 'redraft' | 'blocked' | null;
    }[];
};

/** Server-sent events emitted while a turn runs (app/Agent/AgentEvent.php). */
export type AgentEvent =
    | { type: 'turn.started'; data: { turn: number } }
    | { type: 'safety'; data: Safety }
    | { type: 'agent.thinking'; data: { iteration: number } }
    | {
          type: 'tool.started';
          data: { id: string; name: string; input: Record<string, unknown> };
      }
    | {
          type: 'tool.finished';
          data: {
              id: string;
              name: string;
              is_error: boolean;
              error: string | null;
              stats: { returned: number; total: number } | null;
              duration_ms: number;
          };
      }
    | { type: 'recommendation'; data: Recommendation }
    | {
          type: 'guard';
          data: { action: 'redraft' | 'blocked'; violations: string[] };
      }
    | {
          type: 'message';
          data: { text: string; final: boolean; fallback?: string };
      }
    | { type: 'error'; data: TurnError }
    | { type: 'done'; data: { turn: number; urgency: Urgency | null } };
