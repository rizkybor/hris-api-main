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
        Schema::table('dashboard_widget_layouts', function (Blueprint $table) {
            // "small" (1/3 row width), "medium" (1/2 row width), "large"
            // (full row) -- macOS/iOS-style widget resizing. Null falls
            // back to the widget's registry default (see
            // DashboardWidgetRegistry::WIDGETS) until the user actually
            // resizes it themselves.
            $table->string('size')->nullable()->after('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_widget_layouts', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
