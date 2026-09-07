<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            DepartmentPermissionSeeder::class,
            ExaminationTypeSeeder::class,
            PermissionSeeder::class,
        ]);

        $radiology = Department::query()->where('code', 'RAD')->firstOrFail();

        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin User',
                'email' => 'admin@cdh.test',
                'password' => 'password',
                'department_id' => $radiology->id,
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $staff = User::query()->updateOrCreate(
            ['username' => 'rad.staff'],
            [
                'name' => 'Radiology Staff',
                'email' => 'rad.staff@cdh.test',
                'password' => 'password',
                'department_id' => $radiology->id,
                'role' => 'staff',
                'is_active' => true,
            ],
        );

        $permissionIds = Permission::query()
            ->whereIn('slug', array_column(PermissionEnum::cases(), 'value'))
            ->pluck('id');

        $staff->permissions()->sync($permissionIds);
    }
}
