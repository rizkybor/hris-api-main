<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Defaults are the company office (PT Jendela Cakra Digital,
            // Jl. Pd. Cabe Raya No.7) so an existing settings row starts
            // enforcing the geofence immediately, not with a 0,0 coordinate.
            $table->decimal('office_latitude', 10, 8)->default(-6.34660080)->after('allow_weekend_check_in');
            $table->decimal('office_longitude', 11, 8)->default(106.76173940)->after('office_latitude');
            $table->unsignedInteger('office_radius_meters')->default(150)->after('office_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['office_latitude', 'office_longitude', 'office_radius_meters']);
        });
    }
};
