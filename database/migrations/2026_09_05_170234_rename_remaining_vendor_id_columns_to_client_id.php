<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The earlier rename_vendor_tables_to_client_tables migration only
     * renamed the tables themselves -- these sub-resource tables' own
     * vendor_id-style FK columns were missed and still pointed at the old
     * name until now.
     */
    public function up(): void
    {
        Schema::table('client_evaluations', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'client_id');
        });

        Schema::table('client_attachments', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'client_id');
        });

        Schema::table('client_task_pivots', function (Blueprint $table) {
            $table->renameColumn('vendor_id', 'client_id');
            $table->renameColumn('task_vendor_id', 'task_client_id');
            $table->renameColumn('scope_vendor_id', 'scope_client_id');
            $table->renameColumn('payment_vendor_id', 'payment_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_task_pivots', function (Blueprint $table) {
            $table->renameColumn('client_id', 'vendor_id');
            $table->renameColumn('task_client_id', 'task_vendor_id');
            $table->renameColumn('scope_client_id', 'scope_vendor_id');
            $table->renameColumn('payment_client_id', 'payment_vendor_id');
        });

        Schema::table('client_attachments', function (Blueprint $table) {
            $table->renameColumn('client_id', 'vendor_id');
        });

        Schema::table('client_evaluations', function (Blueprint $table) {
            $table->renameColumn('client_id', 'vendor_id');
        });
    }
};
