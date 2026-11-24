<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apps without licensing: the License Manager can also just keep an app
 * (catalog entry, versions and installers) with no keys at all. Those have
 * no key prefix, so the column becomes optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->boolean('is_licensed')->default(true)->after('name');
            $table->string('website_url', 255)->nullable()->after('description');
            $table->string('key_prefix', 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('license_products', function (Blueprint $table) {
            $table->dropColumn(['is_licensed', 'website_url']);
        });
    }
};
