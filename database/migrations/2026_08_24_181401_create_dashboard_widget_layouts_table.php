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
        Schema::create('dashboard_widget_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Matches a key in App\Support\DashboardWidgetRegistry, not a
            // foreign key -- the widget catalog is code-defined, not a DB
            // table, since widgets map 1:1 to Vue components.
            $table->string('widget_key');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widget_layouts');
    }
};
