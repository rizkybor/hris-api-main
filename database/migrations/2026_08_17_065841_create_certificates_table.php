<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->unique();

            $table->string('title');
            $table->string('recipient_name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('signatory_name');
            $table->string('signatory_title');

            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();

            // Number components, kept individually (not just the formatted
            // string) so the increment-per-scope rule can query on them directly.
            // Explicit (short) lengths: these three sit together in a composite
            // index below, and InnoDB caps a utf8mb4 index at 3072 bytes --
            // the default VARCHAR(255) x3 alone would blow past that.
            $table->string('company_code', 20);
            $table->string('category_code', 50);
            $table->string('program_code', 50);
            $table->unsignedSmallInteger('year');
            $table->string('month_roman', 4);
            $table->unsignedInteger('sequence_number');

            // Groups certificates generated together in one bulk request.
            $table->uuid('batch_id')->nullable();

            $table->string('pdf_path')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('batch_id');
            $table->index(
                ['company_code', 'category_code', 'program_code', 'year', 'month_roman'],
                'certificates_number_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
