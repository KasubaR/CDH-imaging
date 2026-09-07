<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('patient_name')->nullable()->after('id');
            $table->string('nrc')->nullable()->unique()->after('patient_name');
            $table->string('gender')->nullable()->after('nrc');
        });

        if (Schema::hasColumn('patients', 'first_name')) {
            DB::table('patients')->orderBy('id')->chunk(100, function ($patients): void {
                foreach ($patients as $patient) {
                    DB::table('patients')
                        ->where('id', $patient->id)
                        ->update([
                            'patient_name' => trim("{$patient->first_name} {$patient->last_name}"),
                        ]);
                }
            });
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique(['hospital_number']);
            $table->dropColumn(['hospital_number', 'first_name', 'last_name', 'sex', 'phone']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('patient_name')->nullable(false)->change();
            $table->date('date_of_birth')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('hospital_number')->nullable()->after('id');
            $table->string('first_name')->nullable()->after('hospital_number');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('sex')->nullable()->after('date_of_birth');
            $table->string('phone')->nullable()->after('sex');
        });

        DB::table('patients')->orderBy('id')->chunk(100, function ($patients): void {
            foreach ($patients as $patient) {
                $parts = explode(' ', (string) $patient->patient_name, 2);

                DB::table('patients')
                    ->where('id', $patient->id)
                    ->update([
                        'hospital_number' => 'HN-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT),
                        'first_name' => $parts[0] ?? 'Unknown',
                        'last_name' => $parts[1] ?? '',
                        'sex' => $patient->gender ?? 'unknown',
                    ]);
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('hospital_number')->nullable(false)->unique()->change();
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
            $table->string('sex')->nullable(false)->change();
            $table->date('date_of_birth')->nullable(false)->change();
            $table->dropUnique(['nrc']);
            $table->dropColumn(['patient_name', 'nrc', 'gender']);
        });
    }
};
