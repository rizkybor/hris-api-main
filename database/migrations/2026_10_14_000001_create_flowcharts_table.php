<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flowcharts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // The whole diagram as the editor (Vue Flow) serializes it:
            // { nodes: [...], edges: [...], viewport: {x, y, zoom} }. Stored
            // as one document rather than node/edge tables since it's only
            // ever loaded and saved as a whole.
            $table->json('data');

            // Null rather than cascading, so a diagram outlives the account
            // that drew it.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flowcharts');
    }
};
