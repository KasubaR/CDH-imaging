<?php

namespace Database\Seeders;

use App\Models\ExaminationType;
use Illuminate\Database\Seeder;

class ExaminationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Chest', 'code' => 'CHEST', 'description' => 'Chest radiograph examination.'],
            ['name' => 'Abdomen', 'code' => 'ABD', 'description' => 'Abdominal radiograph examination.'],
            ['name' => 'Skull', 'code' => 'SKULL', 'description' => 'Skull radiograph examination.'],
            ['name' => 'Spine', 'code' => 'SPINE', 'description' => 'Spinal radiograph examination.'],
            ['name' => 'Pelvis', 'code' => 'PELVIS', 'description' => 'Pelvic radiograph examination.'],
            ['name' => 'Hip', 'code' => 'HIP', 'description' => 'Hip radiograph examination.'],
            ['name' => 'Knee', 'code' => 'KNEE', 'description' => 'Knee radiograph examination.'],
            ['name' => 'Shoulder', 'code' => 'SHOULDER', 'description' => 'Shoulder radiograph examination.'],
            ['name' => 'Arm', 'code' => 'ARM', 'description' => 'Arm radiograph examination.'],
            ['name' => 'Leg', 'code' => 'LEG', 'description' => 'Leg radiograph examination.'],
            ['name' => 'Foot', 'code' => 'FOOT', 'description' => 'Foot radiograph examination.'],
            ['name' => 'Hand', 'code' => 'HAND', 'description' => 'Hand radiograph examination.'],
        ];

        foreach ($types as $type) {
            ExaminationType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
