<?php

namespace App\Agent\Guardrails;

use App\Models\Conversation;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Recommendation;
use App\Models\ToolCall;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the agent is entitled to reference in this conversation. Two sets,
 * one inside the other:
 *
 *   retrieved  Providers a directory search has returned. Only these IDs
 *              may be put on a recommendation card.
 *   presented  Providers shown to the patient on a card. Only these may be
 *              named in the assistant's prose.
 *
 * The second rule exists because a retrieved name is not the same as a true
 * statement: a model can search without a specialty filter and then describe
 * the cardiologists it got back as oncologists. A card states each doctor's
 * specialty from the database, so tying prose to cards means a provider is
 * never named without the facts about them on screen beside it.
 *
 * Both sets are rebuilt from stored records (the tool-call audit log and the
 * recommendations), so they cannot drift from what actually happened.
 */
class GroundingLedger
{
    /** @var array<int, true> */
    private array $retrievedDoctors = [];

    /** @var array<int, true> */
    private array $retrievedHospitals = [];

    /** @var array<int, true> */
    private array $presentedDoctors = [];

    /** @var array<int, true> */
    private array $presentedHospitals = [];

    public static function forConversation(Conversation $conversation): self
    {
        $ledger = new self;

        $conversation->toolCalls()
            ->where('is_error', false)
            ->get(['doctor_ids', 'hospital_ids'])
            ->each(fn (ToolCall $call) => $ledger->record($call->doctor_ids ?? [], $call->hospital_ids ?? []));

        $conversation->recommendations()
            ->with(['doctors', 'hospitals'])
            ->get()
            ->each(fn (Recommendation $recommendation) => $ledger->markPresented($recommendation->doctors, $recommendation->hospitals));

        return $ledger;
    }

    /**
     * @param  list<int>  $doctorIds
     * @param  list<int>  $hospitalIds
     */
    public function record(array $doctorIds, array $hospitalIds): void
    {
        $this->retrievedDoctors += array_fill_keys($doctorIds, true);
        $this->retrievedHospitals += array_fill_keys($hospitalIds, true);
    }

    /**
     * A card shows each doctor together with their hospital's name, so that
     * hospital counts as presented too.
     *
     * @param  Collection<int, Doctor>  $doctors
     * @param  Collection<int, Hospital>  $hospitals
     */
    public function markPresented(Collection $doctors, Collection $hospitals): void
    {
        foreach ($doctors as $doctor) {
            $this->presentedDoctors[$doctor->id] = true;
            $this->presentedHospitals[$doctor->hospital_id] = true;
        }

        foreach ($hospitals as $hospital) {
            $this->presentedHospitals[$hospital->id] = true;
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int> The IDs no search has returned.
     */
    public function unknownDoctors(array $ids): array
    {
        return array_values(array_filter($ids, fn (int $id) => ! isset($this->retrievedDoctors[$id])));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function unknownHospitals(array $ids): array
    {
        return array_values(array_filter($ids, fn (int $id) => ! isset($this->retrievedHospitals[$id])));
    }

    /**
     * Doctors the model has seen in search results but the patient has not
     * been shown. Their names are the ones most likely to turn up in prose
     * without a card.
     *
     * @return Collection<int, Doctor>
     */
    public function unpresentedDoctors(): Collection
    {
        return Doctor::query()->findMany(array_keys(array_diff_key($this->retrievedDoctors, $this->presentedDoctors)));
    }

    /**
     * @return Collection<int, Hospital>
     */
    public function unpresentedHospitals(): Collection
    {
        return Hospital::query()->findMany(array_keys(array_diff_key($this->retrievedHospitals, $this->presentedHospitals)));
    }

    /**
     * The doctors the assistant may name in prose.
     *
     * @return Collection<int, Doctor>
     */
    public function presentedDoctors(): Collection
    {
        return Doctor::query()->findMany(array_keys($this->presentedDoctors));
    }

    /**
     * The hospitals the assistant may name in prose.
     *
     * @return Collection<int, Hospital>
     */
    public function presentedHospitals(): Collection
    {
        return Hospital::query()->findMany(array_keys($this->presentedHospitals));
    }
}
