<?php

namespace App\Repositories;

use App\DTOs\LicenseDto;
use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseEvent;
use App\Models\LicenseProduct;
use App\Services\LicenseEventLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicenseRepository implements LicenseRepositoryInterface
{
    public const SORT_FIELDS = ['created_at', 'expires_at'];

    public function __construct(private LicenseEventLogger $eventLogger) {}

    public function getAll(
        LicenseProduct $product,
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $plan,
        ?int $limit,
        bool $execute,
        ?string $sortBy = null,
        ?string $sortDir = null,
        ?string $platform = null
    ): Builder|Collection {
        $query = License::query()
            ->forProduct($product)
            ->with('product')
            ->withCount('activations')
            ->withMax('activations', 'last_seen_at')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search(trim($search));
                }
            })
            ->when($status, fn ($query) => $query->status($status))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($plan, fn ($query) => $query->where('plan', $plan))
            ->when($platform, fn ($query) => $query->where('platform', $platform));

        $direction = $sortDir === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'expires_at') {
            // Lifetime licenses (no expiry) always go last.
            $query->orderByRaw('expires_at IS NULL')->orderBy('expires_at', $direction);
        } else {
            $query->orderBy('created_at', $sortBy === 'created_at' ? $direction : 'desc');
        }
        $query->orderByDesc('id');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        LicenseProduct $product,
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $plan,
        int $rowPerPage,
        ?string $sortBy = null,
        ?string $sortDir = null,
        ?string $platform = null
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $product,
            $search,
            $status,
            $type,
            $plan,
            null,
            false,
            $sortBy,
            $sortDir,
            $platform
        );

        return $query->paginate($rowPerPage);
    }

    /**
     * A license of this product; a license of another product is a 404.
     */
    public function getById(
        LicenseProduct $product,
        string $id
    ): License {
        return License::query()
            ->forProduct($product)
            ->with(['product', 'bundleMembers', 'activations' => fn ($query) => $query->orderByDesc('last_seen_at')])
            ->withCount('activations')
            ->withMax('activations', 'last_seen_at')
            ->findOrFail($id);
    }

    public function create(
        LicenseProduct $product,
        array $data
    ): License {
        $licenseDto = LicenseDto::fromArray($data);
        // "both" generates a Mac and an iPad license together (one bundle).
        $platforms = License::platformsFor($licenseDto->platform);
        $bundleId = count($platforms) > 1 ? (string) Str::uuid() : null;

        // The product row is locked while the platforms' counters move, so
        // concurrent creates never get the same number; numbers are never
        // reused. Mac and iPad count separately. A bundle is all or nothing.
        $licenses = DB::transaction(function () use ($product, $licenseDto, $platforms, $bundleId) {
            $locked = LicenseProduct::query()->lockForUpdate()->findOrFail($product->id);
            $created = [];

            foreach ($platforms as $platform) {
                $column = LicenseProduct::sequenceColumn($platform);
                $sequence = $locked->{$column} + 1;
                // The key's last group is 4 digits (the app accepts 20-character keys).
                if ($sequence > License::MAX_SEQUENCE) {
                    $label = License::PLATFORM_LABELS[$platform];
                    throw ValidationException::withMessages([
                        'platform' => "{$locked->name} has reached ".License::MAX_SEQUENCE." {$label} licenses, the most a 4-digit key number allows.",
                    ]);
                }
                $locked->forceFill([$column => $sequence])->save();

                $created[] = License::create(array_merge(
                    $licenseDto->toArray(),
                    [
                        'product_id' => $locked->id,
                        'platform' => $platform,
                        'bundle_id' => $bundleId,
                        'sequence' => $sequence,
                        'key' => License::generateKey($locked->key_prefix, $platform, $licenseDto->plan, $sequence),
                        // Lifetime: the major version it gets updates for.
                        'updates_major' => License::updatesMajorFor($licenseDto->plan, $data['updates_major'] ?? null, $locked),
                        // The terms the license is issued under.
                        'terms_version' => $locked->terms ? $locked->terms_version : null,
                    ]
                ));
            }

            return $created;
        });

        foreach ($licenses as $created) {
            $this->eventLogger->log($created, LicenseEvent::CREATED, ['meta' => array_filter([
                'plan' => $created->plan,
                'platform' => $created->platform,
                'bundle' => $bundleId ? true : null,
                'type' => $created->type,
                'expires_at' => $created->expires_at?->utc()->toIso8601ZuluString(),
                'max_devices' => $created->max_devices,
                // "cli" from license:create, otherwise the back office.
                'source' => $data['source'] ?? 'admin',
            ], fn ($value) => $value !== null)]);
        }

        // The first one (the Mac key of a bundle); its bundle_siblings carry the rest.
        $license = $licenses[0];

        return $this->getById($product, (string) $license->id);
    }

    public function update(
        LicenseProduct $product,
        string $id,
        array $data
    ): License {
        $license = License::forProduct($product)->findOrFail($id);
        $licenseDto = LicenseDto::fromArrayForUpdate($data, $license);

        $original = $license->getRawOriginal();
        // Lifetime keeps (or upgrades to) a major version; other plans get every version.
        $updatesMajor = License::updatesMajorFor(
            $licenseDto->plan,
            array_key_exists('updates_major', $data) ? $data['updates_major'] : $license->updates_major,
            $product
        );
        $license->update(array_merge($licenseDto->toArray(), ['updates_major' => $updatesMajor]));
        $this->eventLogger->logAdminChanges($license, $original, $license->getChanges());

        return $this->getById($product, $id);
    }

    public function delete(
        LicenseProduct $product,
        string $id
    ): License {
        $license = License::forProduct($product)->findOrFail($id);

        DB::transaction(function () use ($license) {
            // Free every device so a restored license starts clean and the
            // key stops refreshing immediately.
            $license->activations()->delete();
            $license->delete();
        });

        $this->eventLogger->log($license, LicenseEvent::DELETED, ['meta' => [
            'key' => $license->key,
            'licensee_name' => $license->licensee_name,
        ]]);

        return $license;
    }

    public function releaseActivation(
        LicenseProduct $product,
        string $licenseId,
        string $activationId
    ): LicenseActivation {
        $license = License::forProduct($product)->findOrFail($licenseId);
        $activation = $license->activations()->findOrFail($activationId);
        $activation->delete();

        activity('License Manager')
            ->performedOn($license)
            ->withProperties([
                'device_id' => $activation->device_id,
                'device_name' => $activation->device_name,
                'platform' => $activation->platform,
            ])
            ->log('Device released');

        $this->eventLogger->log($license, LicenseEvent::DEVICE_RELEASED_BY_ADMIN, [
            'device_id' => $activation->device_id,
            'device_name' => $activation->device_name,
            'platform' => $activation->platform,
            'app_version' => $activation->app_version,
        ]);

        return $activation;
    }

    /**
     * Counts for the dashboard cards of one product. "active" matches the
     * status field (on, not expired, more than 14 days left); "expired"
     * includes expired licenses that are also switched off (same as the
     * filter).
     */
    public function summary(LicenseProduct $product): array
    {
        return array_merge($this->statusCounts(License::query()->forProduct($product)), [
            'expiring_soon_days' => License::EXPIRING_SOON_DAYS,
            'by_plan' => $this->planCounts($product),
        ]);
    }

    /**
     * Overview: the same counts per product, plus all products together.
     */
    public function overview(): array
    {
        $products = LicenseProduct::query()->orderBy('id')->get();

        return [
            'all' => $this->statusCounts(License::query()),
            'expiring_soon_days' => License::EXPIRING_SOON_DAYS,
            'products' => $products->map(fn (LicenseProduct $product) => array_merge([
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'key_prefix' => $product->key_prefix,
                'icon_url' => $product->iconUrl(),
                'is_active' => $product->is_active,
            ], $this->statusCounts(License::query()->forProduct($product)), [
                'by_plan' => $this->planCounts($product),
            ]))->values()->all(),
        ];
    }

    /**
     * {trial, professional, lifetime, organization} => count.
     */
    private function planCounts(LicenseProduct $product): array
    {
        $byPlan = License::query()
            ->forProduct($product)
            ->select('plan', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('plan')
            ->pluck('aggregate', 'plan');

        $plans = [];
        foreach (License::PLANS as $plan) {
            $plans[$plan] = (int) ($byPlan[$plan] ?? 0);
        }

        return $plans;
    }

    private function statusCounts(Builder $base): array
    {
        $count = fn (string $status) => (clone $base)->status($status)->count();

        return [
            'total' => (clone $base)->count(),
            'active' => $count(License::STATUS_ACTIVE),
            'expiring_soon' => $count(License::STATUS_EXPIRING_SOON),
            'expired' => $count(License::STATUS_EXPIRED),
            'inactive' => $count(License::STATUS_INACTIVE),
        ];
    }

    /**
     * Active (switched on, not yet expired) licenses of every product whose
     * expiry falls within the next $days days, soonest first.
     */
    public function expiring(int $days, int $limit): Collection
    {
        return License::query()
            ->with('product')
            ->withCount('activations')
            ->withMax('activations', 'last_seen_at')
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Latest events of every product (deleted licenses included), newest
     * first.
     */
    public function recentEvents(int $limit): Collection
    {
        return LicenseEvent::query()
            ->with(['user:id,name', 'license' => fn ($query) => $query->withTrashed()->with('product')])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function getEventsPaginated(
        LicenseProduct $product,
        string $licenseId,
        ?string $event,
        int $rowPerPage
    ): LengthAwarePaginator {
        $license = License::forProduct($product)->findOrFail($licenseId);

        return LicenseEvent::query()
            ->with('user:id,name')
            ->where('license_id', $license->id)
            ->when($event, fn ($query) => $query->where('event', $event))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($rowPerPage);
    }
}
