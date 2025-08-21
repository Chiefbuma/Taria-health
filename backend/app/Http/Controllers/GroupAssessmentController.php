<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class GroupAssessmentController extends Controller
{
    public function getOnboardingColumns(Request $request)
    {
        try {
            // Define columns suitable for grouping
            $allowedColumns = ['sex', 'age', 'payer', 'clinic'];
            $tableColumns = Schema::getColumnListing('onboardings');
            $groupingColumns = array_intersect($tableColumns, $allowedColumns);

            // Format columns into options for the frontend
            $options = array_map(function ($column) {
                $label = match ($column) {
                    'sex' => 'Sex (Male, Female)',
                    'age' => 'Age Group (1-9, 10-19, etc.)',
                    'payer' => 'Payer (AAR, BRITAM, etc.)',
                    'clinic' => 'Clinic (Westlands, Central, etc.)',
                    default => ucfirst($column),
                };
                return ['value' => $column, 'label' => $label];
            }, $groupingColumns);

            return response()->json(['columns' => $options], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching onboarding columns: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    public function getGroupAssessments(Request $request)
    {
        // Get valid columns for grouping
        $allowedColumns = ['sex', 'age', 'payer', 'clinic'];
        $tableColumns = Schema::getColumnListing('onboardings');
        $validColumns = array_intersect($tableColumns, $allowedColumns);

        // Validate groupBy parameter
        $validator = Validator::make($request->all(), [
            'groupBy' => 'required|in:' . implode(',', $validColumns),
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid groupBy parameter'], 400);
        }

        $groupBy = $request->query('groupBy');

        try {
            // Fetch all onboardings for summary statistics
            $onboardings = DB::table('onboardings')
                ->select('id', 'sex', 'payer', 'clinic', 'age')
                ->get();

            // Compute summary statistics
            $summary = [
                'totalParticipants' => $onboardings->count(),
                'maleCount' => $onboardings->where('sex', 'Male')->count(),
                'femaleCount' => $onboardings->where('sex', 'Female')->count(),
                'payers' => [],
                'clinics' => [],
            ];

            // Count payers and clinics
            $onboardings->groupBy('payer')->each(function ($group, $payer) use (&$summary) {
                $summary['payers'][$payer ?: 'Unknown'] = $group->count();
            });
            $onboardings->groupBy('clinic')->each(function ($group, $clinic) use (&$summary) {
                $summary['clinics'][$clinic ?: 'Unknown'] = $group->count();
            });

            // Group onboardings
            $groups = $onboardings;
            if ($groupBy === 'age') {
                $groups = $onboardings->groupBy(function ($item) {
                    $age = (int) $item->age;
                    if (!$age) return 'Unknown';
                    return floor($age / 10) * 10 . '-' . (floor($age / 10) * 10 + 9);
                });
            } else {
                $groups = $onboardings->groupBy($groupBy, true);
            }

            // Fetch assessments for each group
            $assessments = [];
            foreach ($groups as $group => $items) {
                $onboardingIds = $items->pluck('id')->toArray();
                $groupAssessments = DB::table('weekly_assessments')
                    ->select('id', 'onboarding_id', 'assessment_date', 'hba1c', 'ldl', 'bp', 'weight', 'height', 'bmi', 'serum_creatinine', 'ecg', 'nutrition', 'exercise', 'sleep_mental_health', 'medication_adherence', 'revenue')
                    ->whereIn('onboarding_id', $onboardingIds)
                    ->unionAll(
                        DB::table('three_monthly_assessments')
                            ->select('id', 'onboarding_id', 'assessment_date', 'hba1c', 'ldl', 'bp', 'weight', 'height', 'bmi', 'serum_creatinine', 'ecg', 'nutrition', 'exercise', 'sleep_mental_health', 'medication_adherence', 'revenue')
                            ->whereIn('onboarding_id', $onboardingIds)
                    )
                    ->unionAll(
                        DB::table('six_monthly_assessments')
                            ->select('id', 'onboarding_id', 'assessment_date', 'hba1c', 'ldl', 'bp', 'weight', 'height', 'bmi', 'serum_creatinine', 'ecg', 'nutrition', 'exercise', 'sleep_mental_health', 'medication_adherence', 'revenue')
                            ->whereIn('onboarding_id', $onboardingIds)
                    )
                    ->orderBy('assessment_date', 'desc')
                    ->take(4)
                    ->get();

                $assessments[$group ?: 'Unknown'] = $groupAssessments->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'onboarding_id' => $item->onboarding_id,
                        'assessment_date' => $item->assessment_date,
                        'hba1c' => $item->hba1c ? (float) $item->hba1c : null,
                        'ldl' => $item->ldl ? (float) $item->ldl : null,
                        'bp' => $item->bp,
                        'weight' => $item->weight ? (float) $item->weight : null,
                        'height' => $item->height ? (float) $item->height : null,
                        'bmi' => $item->bmi ? (float) $item->bmi : null,
                        'serum_creatinine' => $item->serum_creatinine ? (float) $item->serum_creatinine : null,
                        'ecg' => $item->ecg,
                        'nutrition' => $item->nutrition,
                        'exercise' => $item->exercise,
                        'sleep_mental_health' => $item->sleep_mental_health,
                        'medication_adherence' => $item->medication_adherence,
                        'revenue' => $item->revenue ? (float) $item->revenue : null,
                    ];
                })->toArray();
            }

            return response()->json([
                'assessments' => $assessments,
                'summary' => $summary,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching group assessments: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }
}