<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Rich-text note only the Project Leader may write -- see
            // ProjectController::update() for the authorization check.
            $table->longText('inspect_note')->nullable()->after('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('inspect_note');
        });
    }
};
