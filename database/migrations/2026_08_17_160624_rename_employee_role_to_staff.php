<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'employee')->update(['name' => 'staff']);
        DB::table('announcements')->where('audience', 'employee')->update(['audience' => 'staff']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'staff')->update(['name' => 'employee']);
        DB::table('announcements')->where('audience', 'staff')->update(['audience' => 'employee']);
    }
};
