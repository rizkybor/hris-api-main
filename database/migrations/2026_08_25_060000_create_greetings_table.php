<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('message');
            // Recurring entries (national holidays, birthdays) match on
            // month+day every year regardless of this column's year; one-time
            // entries (a specific meeting/event) match the exact date.
            $table->date('greeting_date');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->string('type')->default('custom'); // holiday | birthday | meeting | custom -- icon/color only
            $table->string('audience')->default('all'); // all | manager | operational_director | hr | finance | staff
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('greeting_date');
            $table->index('audience');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greetings');
    }
};
