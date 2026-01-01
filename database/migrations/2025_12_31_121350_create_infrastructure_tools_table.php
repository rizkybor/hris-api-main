<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infrastructure_tools', function (Blueprint $table) {
            $table->id();
            $table->string('tech_stack_component');
            $table->string('vendor');
            $table->decimal('monthly_fee', 15, 2);
            $table->decimal('annual_fee', 15, 2);
            $table->date('expired_date');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_tools');
    }
};