<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->boolean('is_half_day')->default(false)->after('total_days');
            // Cloudinary public_id -- same convention as other upload
            // columns (e.g. Attendance::check_in_photo), plus the original
            // filename/mime since this can be a PDF or an image (a doctor's
            // note, unlike a check-in photo which is always a camera JPEG).
            $table->string('attachment_original_name')->nullable()->after('emergency_contact');
            $table->string('attachment_path')->nullable()->after('attachment_original_name');
            $table->string('attachment_mime_type')->nullable()->after('attachment_path');
        });

        // total_days needs to hold 0.5 for a half-day request -- Schema's
        // fluent ->change() needs doctrine/dbal, which isn't installed here,
        // so this alters the column directly instead.
        DB::statement('ALTER TABLE leave_requests MODIFY total_days DECIMAL(4,1) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leave_requests MODIFY total_days INT NOT NULL');

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['is_half_day', 'attachment_original_name', 'attachment_path', 'attachment_mime_type']);
        });
    }
};
