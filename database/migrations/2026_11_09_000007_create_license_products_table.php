<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apps sold through the License Manager. OCCA is the first (and only
     * seeded) product; more are added on the Products page. "code" is the
     * slug in the public API path (/api/licenses/{code}/...) and in the
     * admin URLs.
     */
    public function up(): void
    {
        Schema::create('license_products', function (Blueprint $table) {
            $table->id();
            // Lowercase slug: letters, digits and single hyphens (e.g. map-tools-2).
            $table->string('code', 50)->unique();
            $table->string('name');
            // Uppercase prefix of this product's keys, e.g. OCCA -> OCCA-XXXX-...
            $table->string('key_prefix', 10);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('license_products')->insert([
            [
                'code' => 'occa',
                'name' => 'OCCA',
                'key_prefix' => 'OCCA',
                'description' => 'Orienteering map app for Mac and iPad.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('license_products');
    }
};
