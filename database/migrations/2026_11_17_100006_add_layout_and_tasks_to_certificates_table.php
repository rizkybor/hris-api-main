<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates get a page orientation, an optional link to the employee who
 * received it, an opt-in second page listing that employee's completed
 * tasks, and an optional holographic seal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('orientation', 10)->default('landscape')->after('use_signature');
            $table->boolean('include_tasks')->default(false)->after('orientation');
            $table->boolean('use_hologram')->default(false)->after('include_tasks');
            $table->foreignId('employee_id')->nullable()->after('recipient_name')
                ->constrained('employee_profiles', 'id', 'certificates_employee_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign('certificates_employee_fk');
            $table->dropColumn(['orientation', 'include_tasks', 'use_hologram', 'employee_id']);
        });
    }
};
