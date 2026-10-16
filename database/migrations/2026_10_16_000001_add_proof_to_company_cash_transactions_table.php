<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_cash_transactions', function (Blueprint $table) {
            // Optional proof of the transaction (receipt / transfer slip):
            // Cloudinary public id plus the original name and type, so it
            // can be shown back (image preview, or a PDF link).
            $table->string('proof_file')->nullable()->after('notes');
            $table->string('proof_name')->nullable()->after('proof_file');
            $table->string('proof_mime', 100)->nullable()->after('proof_name');
        });
    }

    public function down(): void
    {
        Schema::table('company_cash_transactions', function (Blueprint $table) {
            $table->dropColumn(['proof_file', 'proof_name', 'proof_mime']);
        });
    }
};
