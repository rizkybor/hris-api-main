<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Category-specific licensee details. licensee_name / licensee_email stay
     * as they are (the token carries licensee_name). Which fields are
     * required depends on the plan's category and is enforced in the
     * requests, so every column here is nullable.
     */
    public function up(): void
    {
        Schema::table('occa_licenses', function (Blueprint $table) {
            // Shared by both categories.
            $table->string('phone', 20)->nullable()->after('licensee_email');
            $table->string('city')->nullable()->after('phone');
            $table->string('country')->nullable()->after('city');

            // Individual only.
            $table->string('occupation')->nullable()->after('country');
            $table->string('affiliation')->nullable()->after('occupation');

            // Organization only.
            // team | community | club | federation
            $table->string('organization_type')->nullable()->after('affiliation');
            $table->string('contact_name')->nullable()->after('organization_type');
            $table->string('contact_position')->nullable()->after('contact_name');
            $table->text('address')->nullable()->after('contact_position');
            $table->string('website')->nullable()->after('address');
            $table->string('tax_id', 50)->nullable()->after('website');
            $table->unsignedInteger('member_count')->nullable()->after('tax_id');

            // Sorting / expiring-soon filters.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('occa_licenses', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn([
                'phone', 'city', 'country', 'occupation', 'affiliation',
                'organization_type', 'contact_name', 'contact_position',
                'address', 'website', 'tax_id', 'member_count',
            ]);
        });
    }
};
