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
        Schema::table('images', function (Blueprint $table) {
            $table->renameColumn('path', 'storage_path');
            $table->renameColumn('size_bytes', 'file_size');
        });

        Schema::table('images', function (Blueprint $table) {
            $table->uuid('uuid')->after('id');
            $table->string('stored_filename')->after('original_filename');
            $table->string('thumbnail_path')->nullable()->after('storage_path');
            $table->string('checksum', 64)->after('file_size');
        });

        Schema::table('images', function (Blueprint $table) {
            $table->unique('uuid');
            $table->unique('checksum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropUnique(['checksum']);
            $table->dropColumn(['uuid', 'stored_filename', 'thumbnail_path', 'checksum']);
        });

        Schema::table('images', function (Blueprint $table) {
            $table->renameColumn('storage_path', 'path');
            $table->renameColumn('file_size', 'size_bytes');
        });
    }
};
