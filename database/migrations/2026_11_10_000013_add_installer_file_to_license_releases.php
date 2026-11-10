<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The installer of a version (OCCA: the Mac .dmg), kept on the private
     * "local" disk so every version can be downloaded again later. Files
     * stay when a version is deleted (soft delete).
     */
    public function up(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->after('download_url');
            $table->string('file_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_name');
            $table->string('file_sha256', 64)->nullable()->after('file_size');
            $table->timestamp('file_uploaded_at')->nullable()->after('file_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'file_size', 'file_sha256', 'file_uploaded_at']);
        });
    }
};
