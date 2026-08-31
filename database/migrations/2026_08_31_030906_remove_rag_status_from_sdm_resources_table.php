<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->dropColumn('rag_status');
        });
    }

    public function down(): void
    {
        Schema::table('sdm_resources', function (Blueprint $table) {
            $table->string('rag_status')->nullable()->after('capacity_target');
        });
    }
};
