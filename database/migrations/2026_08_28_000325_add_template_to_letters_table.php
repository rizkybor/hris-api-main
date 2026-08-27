<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            // Which letterhead background the PDF export is stamped on --
            // 'primary' (right-aligned header) or 'secondary' (centered
            // header, double stripe). Doesn't apply to BAST, which keeps
            // its own distinct centered layout regardless of this value.
            $table->string('template')->default('primary')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
