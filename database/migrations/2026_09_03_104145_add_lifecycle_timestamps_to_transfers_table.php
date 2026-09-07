<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'delivered_at', 'completed_at']);
        });
    }
};
