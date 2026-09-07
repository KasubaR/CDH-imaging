<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Department inbox (DepartmentController::index) filters every load by
        // department_id and, when a status filter is active, by status too.
        Schema::table('transfer_recipients', function (Blueprint $table) {
            $table->index(['department_id', 'status']);
        });

        // Admin dashboard "sent today" / department activity ranking
        // (DashboardStatsService) filters by from_department_id and orders/scans
        // by sent_at.
        Schema::table('transfers', function (Blueprint $table) {
            $table->index(['from_department_id', 'sent_at']);
        });

        // Admin dashboard "images today" and the daily images:purge-expired scan
        // (ImageDeletionService) both filter on created_at.
        Schema::table('images', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfer_recipients', function (Blueprint $table) {
            $table->dropIndex(['department_id', 'status']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropIndex(['from_department_id', 'sent_at']);
        });

        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
