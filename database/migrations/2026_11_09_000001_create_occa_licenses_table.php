<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OCCA Pro license keys sold and managed from the back office.
     */
    public function up(): void
    {
        Schema::create('occa_licenses', function (Blueprint $table) {
            $table->id();
            // OCCA-XXXX-XXXX-XXXX-XXXX, always stored uppercase.
            $table->string('key')->unique();
            $table->string('licensee_name');
            $table->string('licensee_email')->nullable();
            // professional | enterprise | organization
            $table->string('plan')->default('professional');
            // lifetime | term
            $table->string('type')->default('term');
            $table->dateTime('starts_at')->nullable();
            // Null for lifetime licenses.
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('max_devices')->default(2);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->dateTime('last_activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occa_licenses');
    }
};
