<?php

namespace App\Http\Controllers;

use App\Models\MedicationUse;
use App\Models\Onboarding;
use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MedicationUseController extends Controller
{
    public function index($onboardingId)
    {
        $onboarding = Onboarding::findOrFail($onboardingId);
        return response()->json([
            'data' => [
                'medication_uses' => $onboarding->medicationUses()->with('medication')->get()
            ]
        ], 200);
    }

    public function store(Request $request, $onboardingId)
    {
        $validated = $request->validate([
            'medication_id' => ['required', 'exists:medications,id'],
            'onboarding_id' => ['required', 'exists:onboardings,id'],
            'days_supplied' => ['nullable', 'integer', 'min:1', 'max:365'],
            'no_pills_dispensed' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'frequency' => ['nullable', Rule::in(['daily', 'twice_daily', 'weekly', 'as_needed'])],
        ]);

        $medicationUse = MedicationUse::create($validated);
        return response()->json($medicationUse->load('medication'), 201);
    }

    public function update(Request $request, $id)
    {
        $medicationUse = MedicationUse::findOrFail($id);
        $this->authorize('update', $medicationUse);

        $validated = $request->validate([
            'medication_id' => ['required', 'exists:medications,id'],
            'onboarding_id' => ['required', 'exists:onboardings,id'],
            'days_supplied' => ['nullable', 'integer', 'min:1', 'max:365'],
            'no_pills_dispensed' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'frequency' => ['nullable', Rule::in(['daily', 'twice_daily', 'weekly', 'as_needed'])],
        ]);

        $medicationUse->update($validated);
        return response()->json($medicationUse->load('medication'), 200);
    }

    public function destroy($id)
    {
        $medicationUse = MedicationUse::findOrFail($id);
        $this->authorize('delete', $medicationUse);
        $medicationUse->delete();
        return response()->json(null, 204);
    }

    public function getMedications()
    {
        return response()->json([
            'medications' => Medication::all()
        ], 200);
    }

    public function getOnboardings()
    {
        return response()->json([
            'onboardings' => Onboarding::all()
        ], 200);
    }
}
