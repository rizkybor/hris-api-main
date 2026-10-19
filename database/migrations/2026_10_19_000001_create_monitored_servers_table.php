<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address', 64)->nullable();
            $table->text('description')->nullable();

            // The agent authenticates with a random token; only its SHA-256
            // is stored (the plain token is shown once, when issued), plus a
            // short prefix so admins can tell tokens apart.
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);

            // Latest report from the agent: disk, memory, CPU, folder
            // breakdown, host info. Only the latest is kept.
            $table->json('last_report')->nullable();
            $table->timestamp('last_report_at')->nullable();
            $table->string('last_report_ip', 64)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_servers');
    }
};
