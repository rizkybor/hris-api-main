<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The iPad installer of a version (an Ad Hoc signed .ipa), next to the
     * Mac installer (file_*). Kept on the private "local" disk under
     * license-releases/{product}/{version}/ with its size and SHA-256.
     * ipa_meta holds what was read from the IPA on upload: bundle id,
     * version, build and the embedded provisioning profile (type, expiry,
     * provisioned devices).
     */
    public function up(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->string('ipa_path', 500)->nullable()->after('file_uploaded_at');
            $table->string('ipa_name')->nullable()->after('ipa_path');
            $table->unsignedBigInteger('ipa_size')->nullable()->after('ipa_name');
            $table->string('ipa_sha256', 64)->nullable()->after('ipa_size');
            $table->json('ipa_meta')->nullable()->after('ipa_sha256');
            $table->timestamp('ipa_uploaded_at')->nullable()->after('ipa_meta');
        });
    }

    public function down(): void
    {
        Schema::table('license_releases', function (Blueprint $table) {
            $table->dropColumn(['ipa_path', 'ipa_name', 'ipa_size', 'ipa_sha256', 'ipa_meta', 'ipa_uploaded_at']);
        });
    }
};
