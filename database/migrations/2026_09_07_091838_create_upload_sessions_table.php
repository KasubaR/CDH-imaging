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
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('examination_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('declared_mime_type');
            $table->unsignedBigInteger('declared_size')->default(0);
            $table->unsignedInteger('total_chunks');
            $table->string('status')->default('pending');
            $table->foreignId('image_id')->nullable()->constrained()->nullOnDelete();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
