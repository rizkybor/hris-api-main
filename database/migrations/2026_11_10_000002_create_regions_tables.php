<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indonesian administrative regions (Kepmendagri codes). Filled by
     * `php artisan regions:import`, never by a migration.
     */
    public function up(): void
    {
        Schema::create('regions_provinces', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('name');
            $table->index('name');
        });

        Schema::create('regions_regencies', function (Blueprint $table) {
            $table->string('code', 5)->primary();
            $table->string('province_code', 2)->index();
            $table->string('name');
        });

        Schema::create('regions_districts', function (Blueprint $table) {
            $table->string('code', 8)->primary();
            $table->string('regency_code', 5)->index();
            $table->string('name');
        });

        Schema::create('regions_villages', function (Blueprint $table) {
            $table->string('code', 13)->primary();
            $table->string('district_code', 8)->index();
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions_villages');
        Schema::dropIfExists('regions_districts');
        Schema::dropIfExists('regions_regencies');
        Schema::dropIfExists('regions_provinces');
    }
};
