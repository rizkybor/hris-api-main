<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            // Cloudinary public id of a PNG snapshot of the diagram, refreshed
            // by the editor on every save. Lets other documents (Proposal
            // Penawaran) show the flowchart without rendering Vue Flow.
            $table->string('preview_image')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('flowcharts', function (Blueprint $table) {
            $table->dropColumn('preview_image');
        });
    }
};
