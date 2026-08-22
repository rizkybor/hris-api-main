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
        Schema::table('company_abouts', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('npwp')->nullable()->after('legal_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_abouts', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'npwp']);
        });
    }
};
