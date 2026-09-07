<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentPermission;
use Illuminate\Database\Seeder;

class DepartmentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()
            ->whereIn('code', ['RAD', 'OPD', 'PHYS', 'NURS', 'MOIC', 'ORTH'])
            ->get()
            ->keyBy('code');

        $rules = [
            'RAD' => [
                'OPD' => ['can_send' => true, 'can_receive' => true],
                'NURS' => ['can_send' => true, 'can_receive' => true],
                'PHYS' => ['can_send' => true, 'can_receive' => true],
                'MOIC' => ['can_send' => true, 'can_receive' => true],
                'ORTH' => ['can_send' => true, 'can_receive' => true],
            ],
            'OPD' => [
                'NURS' => ['can_send' => true, 'can_receive' => true],
                'PHYS' => ['can_send' => true, 'can_receive' => true],
                'MOIC' => ['can_send' => true, 'can_receive' => true],
                'ORTH' => ['can_send' => true, 'can_receive' => true],
            ],
            'ORTH' => [
                'OPD' => ['can_send' => true, 'can_receive' => true],
                'NURS' => ['can_send' => true, 'can_receive' => true],
                'RAD' => ['can_send' => true, 'can_receive' => true],
            ],
        ];

        foreach ($rules as $fromCode => $destinations) {
            $fromDepartment = $departments->get($fromCode);

            if ($fromDepartment === null) {
                continue;
            }

            foreach ($destinations as $toCode => $flags) {
                $toDepartment = $departments->get($toCode);

                if ($toDepartment === null) {
                    continue;
                }

                DepartmentPermission::query()->updateOrCreate(
                    [
                        'from_department_id' => $fromDepartment->id,
                        'to_department_id' => $toDepartment->id,
                    ],
                    $flags,
                );
            }
        }
    }
}
