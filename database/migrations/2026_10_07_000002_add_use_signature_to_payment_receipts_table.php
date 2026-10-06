<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same per-document signature opt-in as
     * 2026_10_07_000001_add_use_signature_to_document_tables.
     */
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->boolean('use_signature')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropColumn('use_signature');
        });
    }
};
