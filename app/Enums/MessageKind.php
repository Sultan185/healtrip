<?php

namespace App\Enums;

/**
 * Why a row exists in the transcript. Every kind is replayed to the model;
 * only rows with `visible_text` are shown to the patient.
 */
enum MessageKind: string
{
    /** What the patient typed. */
    case UserText = 'user_text';

    /** Operator note carrying the deterministic safety screen's finding. */
    case SafetyNote = 'safety_note';

    /** A model response, stored verbatim (thinking, text and tool_use blocks). */
    case Assistant = 'assistant';

    /** Our tool results answering the previous assistant message. */
    case ToolResults = 'tool_results';

    /** Operator note asking the model to rewrite an ungrounded answer. */
    case GuardCorrection = 'guard_correction';

    /** A safe reply written by the system when the model's could not be used. */
    case Fallback = 'fallback';
}
