<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These stored third-party account passwords were sitting in plaintext.
     * VARCHAR(255) is also too narrow for Laravel's `encrypted` cast, which
     * wraps the ciphertext in a base64-encoded JSON envelope (iv/value/mac)
     * that runs well over 255 characters even for a short plaintext -- so
     * the column is widened to TEXT before the existing rows are encrypted
     * in place. `doctrine/dbal` isn't installed, so this uses a raw ALTER
     * rather than Schema::table(...)->change().
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `credential_accounts` MODIFY `password` TEXT NOT NULL');

        DB::table('credential_accounts')->whereNotNull('password')->orderBy('id')->get(['id', 'password'])
            ->each(function ($row) {
                DB::table('credential_accounts')->where('id', $row->id)->update([
                    'password' => Crypt::encryptString($row->password),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('credential_accounts')->whereNotNull('password')->orderBy('id')->get(['id', 'password'])
            ->each(function ($row) {
                try {
                    $plain = Crypt::decryptString($row->password);
                } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                    // Already plaintext (e.g. migration never fully applied) -- leave as-is.
                    return;
                }

                DB::table('credential_accounts')->where('id', $row->id)->update([
                    'password' => $plain,
                ]);
            });

        DB::statement('ALTER TABLE `credential_accounts` MODIFY `password` VARCHAR(255) NOT NULL');
    }
};
