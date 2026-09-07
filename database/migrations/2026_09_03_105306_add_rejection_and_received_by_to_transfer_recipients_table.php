<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_recipients', function (Blueprint $table) {
            // Who acknowledged receipt — acknowledged_at already records when (it is the
            // "received_at" of the acknowledgement questionnaire); this is the who.
            $table->foreignId('received_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();

            $table->timestamp('rejected_at')->nullable()->after('completed_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_recipients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
