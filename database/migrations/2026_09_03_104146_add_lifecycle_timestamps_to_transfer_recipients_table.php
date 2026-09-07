<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_recipients', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            $table->timestamp('downloaded_at')->nullable()->after('viewed_at');
            $table->timestamp('completed_at')->nullable()->after('downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_recipients', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'downloaded_at', 'completed_at']);
        });
    }
};
