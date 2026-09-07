<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Radiology',
                'code' => 'RAD',
                'can_send' => true,
                'can_receive' => true,
            ],
            [
                'name' => 'OPD',
                'code' => 'OPD',
                'can_send' => true,
                'can_receive' => true,
            ],
            [
                'name' => 'Physiotherapy',
                'code' => 'PHYS',
                'can_send' => false,
                'can_receive' => true,
            ],
            [
                'name' => 'Nursing',
                'code' => 'NURS',
                'can_send' => false,
                'can_receive' => true,
            ],
            [
                'name' => 'Medical Officer in Charge',
                'code' => 'MOIC',
                'can_send' => false,
                'can_receive' => true,
            ],
            [
                'name' => 'Orthopaedics',
                'code' => 'ORTH',
                'can_send' => true,
                'can_receive' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'can_send' => $department['can_send'],
                    'can_receive' => $department['can_receive'],
                    'is_active' => true,
                ],
            );
        }
    }
}
