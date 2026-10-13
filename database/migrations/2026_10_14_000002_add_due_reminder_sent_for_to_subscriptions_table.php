<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // The next_due_date the H-30 reminder email was already sent
            // for, so each billing cycle is reminded exactly once. When an
            // invoice is generated next_due_date moves forward, which no
            // longer matches this value and re-arms the reminder.
            $table->date('due_reminder_sent_for')->nullable()->after('next_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('due_reminder_sent_for');
        });
    }
};
