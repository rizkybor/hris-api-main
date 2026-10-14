<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            // "Sistem Manajemen Order" -- the PDF title reads
            // "Proposal Penawaran — {title} - {client_name}".
            $table->string('title');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            // Kept as text too, so a proposal can go to a prospect that
            // isn't a client record yet, and a renamed client doesn't
            // rewrite a proposal that was already sent.
            $table->string('client_name');
            $table->date('proposal_date');
            // "Lampiran Surat Nomor" -- usually the SPH letter this
            // proposal is attached to.
            $table->string('letter_number')->nullable();
            $table->string('status')->default('draft'); // draft | sent | accepted | rejected
            $table->string('cover_image')->nullable(); // Cloudinary public id

            // Ordered body: [{id, title, content (HTML), page_break_before}].
            // One JSON document since the editor always loads and saves the
            // whole proposal at once.
            $table->json('sections');

            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->boolean('use_signature')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'proposal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
