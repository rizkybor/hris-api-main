<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // How many UAT rounds were agreed with the client (1x, 2x, 3x...);
            // optional, a project may not have UAT terms.
            $table->unsignedTinyInteger('uat_rounds_planned')->nullable()->after('retention_period_months');
        });

        Schema::create('project_uat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // 1, 2, 3 ... in order; kept contiguous when a round is deleted.
            $table->unsignedTinyInteger('round');
            $table->date('date')->nullable();
            // scheduled | in_progress | passed | revision
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            // One finding per line.
            $table->text('findings')->nullable();
            // Signed UAT record (BA UAT), stored on Cloudinary like project documents.
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_type', 20)->nullable();
            $table->string('attachment_size', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_uat_sessions');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('uat_rounds_planned');
        });
    }
};
