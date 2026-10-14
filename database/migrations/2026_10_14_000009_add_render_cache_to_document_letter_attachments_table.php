<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_letter_attachments', function (Blueprint $table) {
            // PDF attachments are stored on Cloudinary as raw files, which it
            // can't turn into images. The first time a memo PDF is exported,
            // a copy is uploaded as an image resource (so each page can be
            // fetched as a JPEG and appended as a Lampiran page); its id and
            // page count are cached here so that only ever happens once.
            $table->string('render_public_id')->nullable()->after('file_path');
            $table->unsignedSmallInteger('page_count')->nullable()->after('render_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_letter_attachments', function (Blueprint $table) {
            $table->dropColumn(['render_public_id', 'page_count']);
        });
    }
};
