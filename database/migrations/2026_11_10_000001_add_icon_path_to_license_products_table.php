<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Each product shows its app icon in the back office. icon_path is a
     * path on the "public" disk (license-products/...). The OCCA icon ships
     * with the repo (resources/license-products/occa.png, 256 px) and is
     * copied to the public disk for the occa product when it has none.
     */
    public function up(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('description');
        });

        $source = resource_path('license-products/occa.png');
        $occa = DB::table('license_products')->where('code', 'occa')->first();

        if ($occa && $occa->icon_path === null && is_file($source)) {
            $path = 'license-products/occa.png';
            Storage::disk('public')->put($path, file_get_contents($source));
            DB::table('license_products')->where('id', $occa->id)->update(['icon_path' => $path, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};
