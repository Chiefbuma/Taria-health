<?php
// database/seeders/UserSeeder.php
namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use App\Models\Designation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // First, get or create the designations we need
        $designations = [
            'Senior Developer' => Designation::firstOrCreate(['name' => 'Senior Developer']),
            'Quality Chair' => Designation::firstOrCreate(['name' => 'Quality Chair']),
            'Treasurer' => Designation::firstOrCreate(['name' => 'Treasurer']),
            'Disbursement Officer' => Designation::firstOrCreate(['name' => 'Disbursement Officer']),
        ];

        // Create staff records first with designation_id
        $staffMembers = [
            [
                'staff_number' => 'STF001',
                'full_name' => 'John Doe',
                'date_of_joining' => '2020-06-15',
                'designation_id' => $designations['Senior Developer']->id,
                'personal_email' => 'john.doe@company.com',
                'business_unit' => 'Engineering',
                'mobile' => '+1 234 567 8900',
                'is_active' => true
            ],
            [
                'staff_number' => 'QC001',
                'full_name' => 'Quality Chair',
                'date_of_joining' => '2019-01-01',
                'designation_id' => $designations['Quality Chair']->id,
                'personal_email' => 'quality.chair@company.com',
                'business_unit' => 'Quality Assurance',
                'mobile' => '+1 234 567 8901',
                'is_active' => true
            ],
            [
                'staff_number' => 'TR001',
                'full_name' => 'Treasurer',
                'date_of_joining' => '2018-03-15',
                'designation_id' => $designations['Treasurer']->id,
                'personal_email' => 'treasurer@company.com',
                'business_unit' => 'Finance',
                'mobile' => '+1 234 567 8902',
                'is_active' => true
            ],
            [
                'staff_number' => 'DS001',
                'full_name' => 'Disbursement Officer',
                'date_of_joining' => '2019-07-20',
                'designation_id' => $designations['Disbursement Officer']->id,
                'personal_email' => 'disbursement@company.com',
                'business_unit' => 'Finance',
                'mobile' => '+1 234 567 8903',
                'is_active' => true
            ]
        ];

        foreach ($staffMembers as $staffData) {
            Staff::create($staffData);
        }

        // Create users with roles
        $users = [
            [
                'email' => 'john.doe@company.com',
                'staff_number' => 'STF001',
                'role' => 'user',
                'password' => Hash::make('password'),
                'is_active' => true
            ],
            [
                'email' => 'quality.chair@company.com',
                'staff_number' => 'QC001',
                'role' => 'chair',
                'password' => Hash::make('password'),
                'is_active' => true
            ],
            [
                'email' => 'treasurer@company.com',
                'staff_number' => 'TR001',
                'role' => 'treasurer',
                'password' => Hash::make('password'),
                'is_active' => true
            ],
            [
                'email' => 'disbursement@company.com',
                'staff_number' => 'DS001',
                'role' => 'disbursement',
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}