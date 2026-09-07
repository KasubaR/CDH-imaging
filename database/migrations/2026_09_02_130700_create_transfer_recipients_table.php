<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['transfer_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_recipients');
    }
};
