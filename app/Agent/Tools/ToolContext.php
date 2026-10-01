<?php

namespace App\Agent\Tools;

use App\Agent\Guardrails\GroundingLedger;
use App\Models\Conversation;

final readonly class ToolContext
{
    public function __construct(
        public Conversation $conversation,
        public int $turn,
        public GroundingLedger $ledger,
    ) {}
}
