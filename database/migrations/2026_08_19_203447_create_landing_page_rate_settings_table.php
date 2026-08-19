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
        Schema::create('landing_page_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('server_dedicated_price', 15, 2)->default(2000000);
            $table->decimal('server_shared_price', 15, 2)->default(1000000);
            $table->decimal('design_dedicated_price', 15, 2)->default(4000000);
            $table->decimal('design_template_price', 15, 2)->default(1500000);
            $table->decimal('default_rate_developer', 15, 2)->default(100000);
            $table->decimal('margin_percent', 5, 2)->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_rate_settings');
    }
};
