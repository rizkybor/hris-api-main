<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an employee's contract or probation end date passes, their login is
 * switched off automatically (employees:expire-access). The date and the
 * reason are kept: the Staff Report's "tenure" period ends on that date,
 * and the same date is never processed twice (a manual reactivation sticks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('access_ended_on')->nullable()->after('is_active');
            $table->string('access_end_reason', 20)->nullable()->after('access_ended_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_ended_on', 'access_end_reason']);
        });
    }
};
