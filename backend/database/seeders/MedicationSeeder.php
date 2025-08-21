<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Medication;

class MedicationSeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $medications = [
            [
                'item_name' => 'Metformin',
                'description' => 'Oral antidiabetic drug for type 2 diabetes',
                'dosage' => '500 mg',
                'frequency' => 'Twice daily',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_name' => 'Amlodipine',
                'description' => 'Calcium channel blocker for hypertension',
                'dosage' => '5 mg',
                'frequency' => 'Once daily',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_name' => 'Atorvastatin',
                'description' => 'Statin for cholesterol management',
                'dosage' => '20 mg',
                'frequency' => 'Once daily at bedtime',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_name' => 'Levothyroxine',
                'description' => 'Thyroid hormone replacement',
                'dosage' => '100 mcg',
                'frequency' => 'Once daily in the morning',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'item_name' => 'Lisinopril',
                'description' => 'ACE inhibitor for blood pressure control',
                'dosage' => '10 mg',
                'frequency' => 'Once daily',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($medications as $medication) {
            Medication::create($medication);
        }
    }
}