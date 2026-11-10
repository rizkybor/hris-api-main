<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The first version list of OCCA (resources/license-products/releases/
     * occa.json): version 1.0.0 with every feature of the first release.
     * Added as a draft; it is published from the back office on release
     * day. Versions already in the table are left alone.
     */
    public function up(): void
    {
        $productId = DB::table('license_products')->where('code', 'occa')->value('id');
        $path = resource_path('license-products/releases/occa.json');
        if (! $productId || ! is_file($path)) {
            return;
        }

        foreach (json_decode(file_get_contents($path), true) ?? [] as $release) {
            if (DB::table('license_releases')->where('product_id', $productId)->where('version', $release['version'])->exists()) {
                continue;
            }
            [$major, $minor, $patch] = array_map('intval', explode('.', $release['version']));

            DB::table('license_releases')->insert([
                'product_id' => $productId,
                'version' => $release['version'],
                'major' => $major,
                'minor' => $minor,
                'patch' => $patch,
                'build' => $release['build'] ?? null,
                'platforms' => $release['platforms'] ?? 'both',
                'title' => $release['title'] ?? null,
                'status' => 'draft',
                'released_at' => null,
                'is_critical' => (bool) ($release['is_critical'] ?? false),
                'changes' => json_encode($release['changes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $productId = DB::table('license_products')->where('code', 'occa')->value('id');
        if ($productId) {
            DB::table('license_releases')->where('product_id', $productId)->where('version', '1.0.0')->where('status', 'draft')->delete();
        }
    }
};
