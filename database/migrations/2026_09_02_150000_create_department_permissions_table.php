<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('to_department_id')->constrained('departments')->restrictOnDelete();
            $table->boolean('can_send')->default(false);
            $table->boolean('can_receive')->default(false);
            $table->timestamps();

            $table->unique(['from_department_id', 'to_department_id'], 'department_permissions_from_to_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_permissions');
    }
};
