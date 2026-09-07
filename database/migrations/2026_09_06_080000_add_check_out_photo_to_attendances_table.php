<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Cloudinary public_id, same convention as check_in_photo --
            // nullable at the DB level since rows recorded before this
            // column existed have none; the check-out request itself
            // requires it going forward.
            $table->string('check_out_photo')->nullable()->after('check_out_long');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('check_out_photo');
        });
    }
};
