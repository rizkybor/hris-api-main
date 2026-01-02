<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors_task_pivots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');            

            $table->foreignId('task_vendor_id')->nullable()->constrained('vendors_task_lists')->onDelete('set null');
$table->foreignId('scope_vendor_id')->nullable()->constrained('vendors_task_scopes')->onDelete('set null');
$table->foreignId('payment_vendor_id')->nullable()->constrained('vendors_task_payments')->onDelete('set null');

            $table->boolean('maintenance')->default(false);
            $table->decimal('contract_value', 15, 2)->nullable();
            $table->string('contract_status')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors_task_pivots');
    }
};