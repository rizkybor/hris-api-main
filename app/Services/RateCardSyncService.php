<?php

namespace App\Services;

use App\Models\ServiceItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pulls the Rate Card (a public Notion database) into the service catalog:
 * name, category, "Start from Rp ..." price, duration, availability. Rows are
 * matched to catalog items by code, the slug of the name before its "(...)"
 * -- "JCD Compro Lite (Static Web)" -> jcd-compro-lite.
 *
 * This reads Notion's public page API (the one notion.site pages use), which
 * isn't an official API; if it changes, prices stay editable by hand.
 */
class RateCardSyncService
{
    /** @return array{created: int, updated: int, rows: int} */
    public function sync(): array
    {
        $rows = $this->fetchRows();
        $created = $updated = 0;

        foreach ($rows as $i => $row) {
            $item = ServiceItem::firstOrNew(['code' => $row['code']]);
            $item->exists ? $updated++ : $created++;
            $item->fill([
                ...$row,
                // Hand-tuned fields aren't overwritten on existing items.
                'pricing_mode' => $item->pricing_mode ?? 'rate_card',
                'sort_order' => $item->exists ? $item->sort_order : ($i + 1) * 10,
                'synced_at' => now(),
            ])->save();
        }

        return ['created' => $created, 'updated' => $updated, 'rows' => count($rows)];
    }

    public function fetchRows(): array
    {
        $cfg = config('services.rate_card');
        $response = Http::timeout(20)->acceptJson()->post(rtrim($cfg['notion_site'], '/').'/api/v3/queryCollection', [
            'collection' => ['id' => $cfg['collection_id'], 'spaceId' => $cfg['space_id']],
            'collectionView' => ['id' => $cfg['view_id'], 'spaceId' => $cfg['space_id']],
            'loader' => [
                'type' => 'reducer',
                'reducers' => ['collection_group_results' => ['type' => 'results', 'limit' => 500]],
                'searchQuery' => '',
                'userTimeZone' => 'Asia/Jakarta',
            ],
        ]);

        if (! $response->ok()) {
            throw new RuntimeException('Rate Card could not be read (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $records = $data['recordMap'] ?? [];
        $schema = collect($records['collection'] ?? [])->map(fn ($c) => $c['value']['value'] ?? $c['value'] ?? [])->first()['schema'] ?? [];
        $columns = collect($schema)->mapWithKeys(fn ($col, $key) => [$key => $col['name'] ?? $key]);
        $ids = $data['result']['reducerResults']['collection_group_results']['blockIds'] ?? [];

        $rows = [];
        foreach ($ids as $id) {
            $block = $records['block'][$id]['value'] ?? [];
            $block = $block['value'] ?? $block;
            $props = collect($block['properties'] ?? [])
                ->mapWithKeys(fn ($v, $k) => [$columns[$k] ?? $k => collect($v)->map(fn ($seg) => $seg[0] ?? '')->implode('')]);
            if ($row = $this->toItem($id, $props->all())) {
                $rows[] = $row;
            }
        }

        if (! $rows) {
            throw new RuntimeException('Rate Card returned no rows -- check the Notion page is still public.');
        }

        return $rows;
    }

    /** One Notion row -> catalog fields. */
    public function toItem(string $id, array $p): ?array
    {
        $name = trim($p['Nama Layanan'] ?? '');
        if ($name === '') {
            return null;
        }

        $priceText = trim($p['Rentang Harga (IDR)'] ?? '');
        $preparation = stripos($priceText, 'PREPARATION') !== false;
        $price = preg_match('/Rp\s*([\d.,]+)/i', $priceText, $m) ? (float) str_replace(['.', ','], ['', '.'], $m[1]) : null;
        $unit = preg_match('/\/\s*(bln|bulan|month)/i', $priceText) ? 'month' : (preg_match('/\/\s*(thn|tahun|year)/i', $priceText) ? 'year' : 'once');
        $description = trim($p['Deskripsi Singkat'] ?? '');

        return [
            'code' => Str::slug(trim(Str::before($name, '('))),
            'name' => rtrim($name, ' )').(str_contains($name, '(') ? ')' : ''),
            'category' => trim($p['Kategori'] ?? '') ?: 'Lainnya',
            'description' => strcasecmp($description, 'COMING SOON') === 0 ? null : ($description ?: null),
            'price_from' => $preparation ? null : $price,
            'price_unit' => $unit,
            'duration_text' => ($d = trim($p['Estimasi Waktu'] ?? '')) && $d !== '-' ? $d : null,
            'availability' => $preparation ? 'preparation' : 'available',
            'rate_card_ref' => $id,
        ];
    }
}
