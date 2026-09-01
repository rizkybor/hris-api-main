<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_assets', function (Blueprint $table) {
            $table->date('warranty_expiry_date')->nullable()->after('purchase_price');
            // Straight-line is the only method actually computed today (see
            // CompanyAsset::getCurrentBookValueAttribute()) -- this column
            // exists so a different method can be recorded/selected later
            // without another migration, not because more than one is
            // implemented yet.
            $table->unsignedSmallInteger('useful_life_months')->nullable()->after('warranty_expiry_date');
            $table->string('depreciation_method')->nullable()->default('straight_line')->after('useful_life_months');
            // Denormalized from asset_maintenance_logs.next_due_date (the
            // latest log entry's value) so "assets due for maintenance
            // soon" can be queried/sorted directly without a join.
            $table->date('next_maintenance_due_date')->nullable()->after('depreciation_method');
            $table->foreignId('supplier_vendor_id')->nullable()->after('next_maintenance_due_date')->constrained('vendors')->nullOnDelete();
        });

        Schema::create('asset_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('company_assets')->cascadeOnDelete();
            $table->date('performed_at');
            $table->text('description');
            $table->decimal('cost', 12, 2)->nullable();
            $table->date('next_due_date')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_logs');

        Schema::table('company_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_vendor_id');
            $table->dropColumn(['warranty_expiry_date', 'useful_life_months', 'depreciation_method', 'next_maintenance_due_date']);
        });
    }
};
