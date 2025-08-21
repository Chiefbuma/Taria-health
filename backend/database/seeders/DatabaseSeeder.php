<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            ClinicsTableSeeder::class,
            AdminUserSeeder::class,
            MedicationSeeder::class,

            // Other seeders...
        ]);
    }
}