<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-use over-the-air install links for the iPad installer (.ipa)
     * of a version: one link installs the app once. The token is stored
     * as a SHA-256 hash (lookup) and encrypted with APP_KEY (so the back
     * office can show an active link again); a database dump alone does
     * not reveal working links.
     *
     * status: active | used | revoked. "expired" is computed on read from
     * expires_at. download_lease_* lets only one download of a link run at
     * a time (renewed while streaming, free again when it stops).
     */
    public function up(): void
    {
        Schema::create('license_install_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('license_releases')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->string('device_name')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('active');
            $table->timestamp('used_at')->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->string('used_user_agent', 500)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('page_opened_at')->nullable();
            $table->timestamp('manifest_fetched_at')->nullable();
            $table->unsignedInteger('download_attempts')->default(0);
            $table->string('download_lease_id', 40)->nullable();
            $table->timestamp('download_lease_until')->nullable();
            $table->timestamps();

            $table->index(['release_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_install_links');
    }
};
