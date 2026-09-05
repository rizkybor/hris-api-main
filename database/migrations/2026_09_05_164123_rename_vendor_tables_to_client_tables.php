<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Vendor" in this codebase always meant the paying client, never a
     * supplier -- renamed throughout (tables, models, routes, permissions)
     * to stop that confusion. Company Asset's genuine supplier reference
     * is handled separately (see change_company_assets_supplier_fk_to_suppliers_table),
     * since that one really is a money-out relationship.
     */
    public function up(): void
    {
        Schema::rename('vendors', 'clients');
        Schema::rename('vendor_evaluations', 'client_evaluations');
        Schema::rename('vendors_attachments', 'client_attachments');
        Schema::rename('vendors_task_lists', 'client_task_lists');
        Schema::rename('vendors_task_payments', 'client_task_payments');
        Schema::rename('vendors_task_pivots', 'client_task_pivots');
        Schema::rename('vendors_task_scopes', 'client_task_scopes');

        Schema::table('projects', function ($table) {
            $table->renameColumn('vendor_id', 'client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function ($table) {
            $table->renameColumn('client_id', 'vendor_id');
        });

        Schema::rename('clients', 'vendors');
        Schema::rename('client_evaluations', 'vendor_evaluations');
        Schema::rename('client_attachments', 'vendors_attachments');
        Schema::rename('client_task_lists', 'vendors_task_lists');
        Schema::rename('client_task_payments', 'vendors_task_payments');
        Schema::rename('client_task_pivots', 'vendors_task_pivots');
        Schema::rename('client_task_scopes', 'vendors_task_scopes');
    }
};
