<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendors_attachments', function (Blueprint $table) {
            $table->id();

            // Kolom relasi ke vendor
            $table->unsignedBigInteger('vendor_id');

            $table->string('document_name');
            $table->string('document_path');
            $table->string('type_file')->nullable();
            $table->string('size_file')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign key ke tabel vendors
            $table->foreign('vendor_id')
                  ->references('id')
                  ->on('vendors')
                  ->onDelete('cascade'); // kalau vendor dihapus, attachment ikut terhapus
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors_attachments');
    }
};
