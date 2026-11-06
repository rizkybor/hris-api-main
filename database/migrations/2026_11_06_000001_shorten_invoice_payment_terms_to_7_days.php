<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Payment terms went from 14 to 7 days (the form defaults changed on
 * 2026-10-14). Invoices and subscriptions saved before that still carry the
 * old sentence; only that exact sentence is swapped, the rest of each
 * terms text is left as written.
 */
return new class extends Migration
{
    private const OLD = 'Payment is due within 14 days of the invoice date.';

    private const NEW = 'Payment is due within 7 days of the invoice date.';

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        foreach (['invoices', 'subscriptions'] as $table) {
            DB::table($table)->where('terms', 'like', '%'.$from.'%')->orderBy('id')->each(function ($row) use ($table, $from, $to) {
                DB::table($table)->where('id', $row->id)->update(['terms' => str_replace($from, $to, $row->terms)]);
            });
        }
    }
};
