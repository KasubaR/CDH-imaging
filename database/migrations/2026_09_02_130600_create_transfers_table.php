<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->text('message')->nullable();
            $table->string('status')->default('sent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
