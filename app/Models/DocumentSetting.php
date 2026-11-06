<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App-wide settings for generated documents. Always a single row.
 */
class DocumentSetting extends Model
{
    protected $fillable = ['signatory_show_degree'];

    protected $attributes = ['signatory_show_degree' => true];

    protected function casts(): array
    {
        return ['signatory_show_degree' => 'boolean'];
    }

    /** Memoized per request -- PDFs read it once per signature block. */
    private static ?self $current = null;

    public static function current(): self
    {
        return self::$current ??= (self::query()->first() ?? self::query()->create([]));
    }

    public static function forget(): void
    {
        self::$current = null;
    }
}
