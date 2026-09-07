<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->renameColumn('requesting_department_id', 'referring_department_id');
            $table->renameColumn('requested_by', 'created_by');
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->string('body_part')->default('')->after('examination_type_id');
            $table->text('description')->nullable()->after('body_part');
            $table->date('date_taken')->nullable()->after('description');
            $table->time('time_taken')->nullable()->after('date_taken');
            $table->string('referring_clinician')->nullable()->after('referring_department_id');
            $table->string('radiographer')->nullable()->after('referring_clinician');
        });

        if (Schema::hasColumn('examinations', 'requested_at')) {
            DB::table('examinations')->orderBy('id')->chunk(100, function ($examinations): void {
                foreach ($examinations as $examination) {
                    DB::table('examinations')
                        ->where('id', $examination->id)
                        ->update([
                            'date_taken' => date('Y-m-d', strtotime((string) $examination->requested_at)),
                            'time_taken' => date('H:i:s', strtotime((string) $examination->requested_at)),
                        ]);
                }
            });
        }

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn(['status', 'notes', 'requested_at', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->string('status')->default('requested');
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn([
                'body_part',
                'description',
                'date_taken',
                'time_taken',
                'referring_clinician',
                'radiographer',
            ]);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->renameColumn('referring_department_id', 'requesting_department_id');
            $table->renameColumn('created_by', 'requested_by');
        });
    }
};
