<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // All nullable -- a vendor can be an individual (perorangan)
            // rather than a registered company, so none of these are
            // guaranteed to exist.
            $table->string('npwp')->nullable()->after('field');
            $table->string('siup_number')->nullable()->after('npwp');
            $table->string('nib_number')->nullable()->after('siup_number');
        });

        Schema::create('vendor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('notes')->nullable();
            $table->date('evaluated_at');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_evaluations');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'siup_number', 'nib_number']);
        });
    }
};
