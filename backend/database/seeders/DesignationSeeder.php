<?php
// database/seeders/DesignationSeeder.php
namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run()
    {
        $designations = [
            ['name' => 'Senior Developer'],
            ['name' => 'Quality Chair'],
            ['name' => 'Treasurer'],
            ['name' => 'Disbursement Officer'],
            ['name' => 'Doctor'],
            ['name' => 'Nurse'],
            ['name' => 'Administrator'],
            ['name' => 'Manager'],
            ['name' => 'Receptionist'],
            ['name' => 'Technician'],
        ];

        foreach ($designations as $designation) {
            Designation::firstOrCreate($designation);
        }
    }
}