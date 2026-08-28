<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category'); // IT-HW, IT-SW, OF-EQ, OF-FN, M-VEH, M-MISC (see CompanyAssetController::CATEGORIES)
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('condition')->default('good'); // good, fair, damaged
            $table->string('status')->default('available'); // available, assigned, maintenance, retired, lost
            $table->foreignId('assigned_to')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->date('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_assets');
    }
};
