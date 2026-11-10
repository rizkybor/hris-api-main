<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The major version a license gets updates for: a Lifetime license
     * covers one major version and all its updates (e.g. 1 = OCCA 1.x);
     * a later major version is a paid upgrade that raises this number.
     * Null = every version while the license runs (Trial, Professional,
     * Organization). Existing Lifetime licenses cover version 1.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedSmallInteger('updates_major')->nullable()->after('type');
        });

        DB::table('licenses')->where('plan', 'lifetime')->update(['updates_major' => 1]);
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('updates_major');
        });
    }
};
