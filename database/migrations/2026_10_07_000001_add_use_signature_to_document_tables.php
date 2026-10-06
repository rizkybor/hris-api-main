<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-document opt-in for stamping the company signatory's scanned
     * signature onto the exported PDF (see App\Support\SignatureImage).
     * Defaults to false so every existing document keeps exporting blank.
     */
    private const TABLES = ['letters', 'purchase_orders', 'document_letters', 'certificates'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('use_signature')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('use_signature');
            });
        }
    }
};
