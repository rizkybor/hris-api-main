<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            // Optional structured item list, reused by BAST (handover items) and
            // Surat Penawaran (offered items/pricing). Null for a plain letter.
            $table->json('items')->nullable()->after('body');

            // Optional second-party signature block, used by BAST / NDA / Surat
            // Penawaran which are signed by both parties. Null for a plain letter
            // that only carries the company's own signature.
            $table->string('second_party_name')->nullable()->after('signatory_title');
            $table->string('second_party_signatory_name')->nullable()->after('second_party_name');
            $table->string('second_party_signatory_title')->nullable()->after('second_party_signatory_name');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn(['items', 'second_party_name', 'second_party_signatory_name', 'second_party_signatory_title']);
        });
    }
};
