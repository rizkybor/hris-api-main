<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\LicenseReleaseResource;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseInstallLink;
use App\Models\LicenseProduct;
use App\Models\LicenseRelease;
use App\Services\IpaInspector;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Version list (release history) of a product: every version with its
 * new features, improvements and bug fixes. Own permission module
 * license-release-*: reading needs -menu, -list or -view, adding -create,
 * editing (incl. publishing and uploading the installer) -edit, deleting
 * -delete, downloading the installer -download. Every version keeps its installers on the private
 * disk: the Mac .dmg or Windows .msi (file_*) and the iPad .ipa (ipa_*).
 * A Mac version is published only with the .dmg, a Windows version only
 * with the .msi / .msix, an iPad version only with the .ipa, a
 * shared version with both.
 */
class LicenseReleaseController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['license-release-menu|license-release-list|license-release-view']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['license-release-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['license-release-edit']), only: ['update', 'uploadFile', 'deleteFile', 'uploadIpa', 'deleteIpa']),
            new Middleware(PermissionMiddleware::using(['license-release-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['license-release-download']), only: ['downloadFile', 'downloadIpa']),
        ];
    }

    public function index(Request $request, string $product)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(LicenseRelease::STATUSES)],
            'platform' => ['nullable', Rule::in(License::PLATFORMS)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $model = LicenseProduct::findByCode($product);
        if (! $model) {
            return $this->notFound();
        }

        $releases = $model->releases()
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->forPlatform($request->platform)
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('version', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('changes', 'like', "%{$search}%")))
            ->ordered()
            ->get();
        $this->withCoverage($model, $releases);

        return ResponseHelper::jsonResponse(true, 'Releases Retrieved Successfully', [
            'current_major' => $model->currentMajor(),
            'latest_version' => $model->latestRelease()?->version,
            // Which app versions the activated devices run (from activate / refresh).
            'versions_in_use' => LicenseActivation::query()
                ->whereHas('license', fn ($q) => $q->forProduct($model))
                ->selectRaw('app_version, platform, count(*) as devices')
                ->groupBy('app_version', 'platform')
                ->get()
                ->map(fn ($row) => ['version' => $row->app_version, 'platform' => $row->platform, 'devices' => (int) $row->devices])
                ->sortByDesc(fn ($row) => LicenseRelease::parse($row['version']) ?? [0, 0, 0])
                ->values(),
            'releases' => LicenseReleaseResource::collection($releases),
        ], 200);
    }

    public function show(string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'Release Retrieved Successfully', new LicenseReleaseResource($item), 200);
    }

    public function store(Request $request, string $product)
    {
        $model = LicenseProduct::findByCode($product);
        if (! $model) {
            return $this->notFound();
        }
        $data = $request->validate($this->rules($model), $this->messages());
        $this->assertPublishable(new LicenseRelease($data));

        $item = $model->releases()->create($this->prepare($data));
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'Release Created Successfully', new LicenseReleaseResource($item), 201);
    }

    public function update(Request $request, string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $data = $request->validate($this->rules($model, $item), $this->messages());
        $this->assertPublishable((clone $item)->fill($data));

        $item->update($this->prepare($data, $item));
        $item = $item->fresh();
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'Release Updated Successfully', new LicenseReleaseResource($item), 200);
    }

    public function destroy(string $product, string $release)
    {
        [, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $item->delete();

        return ResponseHelper::jsonResponse(true, 'Release Deleted Successfully', null, 200);
    }

    /**
     * Upload (or replace) the installer of a version: multipart "file"
     * (.dmg, .zip or .pkg; for a Windows version .msi, .msix or .exe; up
     * to 2 GB). Stored under
     * license-releases/{product}/{version}/ with its size and SHA-256.
     */
    public function uploadFile(Request $request, string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $extensions = $item->fileExtensions();
        $request->validate([
            'file' => ['required', 'file', 'extensions:'.implode(',', $extensions), 'max:'.LicenseRelease::FILE_MAX_KB],
        ], [
            'file.extensions' => match ($item->platforms) {
                License::PLATFORM_WINDOWS => 'Upload the Windows installer as an .msi (or .msix / .exe) file.',
                LicenseRelease::PLATFORM_ANDROID => 'Upload the Android app as an .apk (or .aab) file.',
                LicenseRelease::PLATFORMS_MULTI => 'Upload the installer as one of: .'.implode(', .', $extensions).'.',
                default => 'Upload the installer as a .dmg (or .zip / .pkg) file.',
            },
            'file.max' => 'The installer may be at most 2 GB.',
        ]);

        $file = $request->file('file');
        $name = Str::of(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->slug('-')->limit(80, '')->whenEmpty(fn () => Str::of($model->code))
            .'-'.$item->version.'.'.strtolower($file->getClientOriginalExtension());
        $directory = "license-releases/{$model->code}/{$item->version}";
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs($directory, $name, LicenseRelease::FILE_DISK);

        // One installer per version: a replaced file is removed.
        if ($item->file_path && $item->file_path !== $path) {
            Storage::disk(LicenseRelease::FILE_DISK)->delete($item->file_path);
        }
        $item->update([
            'file_path' => $path,
            'file_name' => $name,
            'file_size' => $file->getSize(),
            'file_sha256' => $sha256,
            'file_uploaded_at' => now(),
        ]);
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'Installer Uploaded Successfully', new LicenseReleaseResource($item), 200);
    }

    /**
     * Download a version's installer (back office).
     */
    public function downloadFile(string $product, string $release)
    {
        [, $item] = $this->find($product, $release);
        if (! $item || ! $item->hasFile() || ! Storage::disk(LicenseRelease::FILE_DISK)->exists($item->file_path)) {
            return $this->notFound();
        }

        return Storage::disk(LicenseRelease::FILE_DISK)->download($item->file_path, $item->file_name);
    }

    /**
     * Remove the installer of a draft. A published Mac version keeps its
     * installer (replace it with a new upload instead).
     */
    public function deleteFile(string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        if ($item->isPublished() && $item->needsFile()) {
            throw ValidationException::withMessages(['file' => 'A published version keeps its installer. Upload a new file to replace it.']);
        }
        if ($item->file_path) {
            Storage::disk(LicenseRelease::FILE_DISK)->delete($item->file_path);
        }
        $item->update(['file_path' => null, 'file_name' => null, 'file_size' => null, 'file_sha256' => null, 'file_uploaded_at' => null]);
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'Installer Removed Successfully', new LicenseReleaseResource($item), 200);
    }

    /**
     * Upload (or replace) the iPad installer of a version: multipart "file"
     * (.ipa, up to 2 GB). The app's Info.plist must carry the product's
     * bundle id (config licensing.ios_bundle_ids) and this version as
     * CFBundleShortVersionString; a different build number, a missing,
     * expired or non-Ad Hoc provisioning profile are warnings.
     */
    public function uploadIpa(Request $request, string $product, string $release, IpaInspector $inspector)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        $request->validate([
            'file' => ['required', 'file', 'extensions:'.implode(',', LicenseRelease::IPA_EXTENSIONS), 'max:'.LicenseRelease::FILE_MAX_KB],
        ], [
            'file.extensions' => 'Upload the iPad installer as an .ipa file.',
            'file.max' => 'The installer may be at most 2 GB.',
        ]);

        $file = $request->file('file');
        try {
            $meta = $inspector->inspect($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }
        $meta['warnings'] = $this->ipaWarnings($model, $item, $meta);

        $name = Str::of($model->code)->slug('-')->limit(80, '')->whenEmpty(fn () => Str::of('app'))
            .'-'.$item->version.'.ipa';
        $directory = "license-releases/{$model->code}/{$item->version}";
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs($directory, $name, LicenseRelease::FILE_DISK);

        if ($item->ipa_path && $item->ipa_path !== $path) {
            Storage::disk(LicenseRelease::FILE_DISK)->delete($item->ipa_path);
        }
        $item->update([
            'ipa_path' => $path,
            'ipa_name' => $name,
            'ipa_size' => $file->getSize(),
            'ipa_sha256' => $sha256,
            'ipa_meta' => $meta,
            'ipa_uploaded_at' => now(),
        ]);
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, $meta['warnings'] ? 'iPad Installer Uploaded With Warnings' : 'iPad Installer Uploaded Successfully', new LicenseReleaseResource($item), 200);
    }

    public function downloadIpa(string $product, string $release)
    {
        [, $item] = $this->find($product, $release);
        if (! $item || ! $item->hasIpa() || ! Storage::disk(LicenseRelease::FILE_DISK)->exists($item->ipa_path)) {
            return $this->notFound();
        }

        return Storage::disk(LicenseRelease::FILE_DISK)->download($item->ipa_path, $item->ipa_name);
    }

    /**
     * Remove the iPad installer of a draft (a published iPad version keeps
     * it). Install links of the version stop working.
     */
    public function deleteIpa(string $product, string $release)
    {
        [$model, $item] = $this->find($product, $release);
        if (! $item) {
            return $this->notFound();
        }
        if ($item->isPublished() && $item->needsIpa()) {
            throw ValidationException::withMessages(['file' => 'A published version keeps its iPad installer. Upload a new file to replace it.']);
        }
        if ($item->ipa_path) {
            Storage::disk(LicenseRelease::FILE_DISK)->delete($item->ipa_path);
        }
        $item->update(['ipa_path' => null, 'ipa_name' => null, 'ipa_size' => null, 'ipa_sha256' => null, 'ipa_meta' => null, 'ipa_uploaded_at' => null]);
        $this->withCoverage($model, collect([$item]));

        return ResponseHelper::jsonResponse(true, 'iPad Installer Removed Successfully', new LicenseReleaseResource($item), 200);
    }

    /**
     * Refuses an .ipa of another app or another version; returns the
     * warnings (build number, provisioning profile).
     *
     * @return string[]
     */
    private function ipaWarnings(LicenseProduct $product, LicenseRelease $release, array $meta): array
    {
        $expectedBundle = config("licensing.ios_bundle_ids.{$product->code}");
        if ($expectedBundle && $meta['bundle_id'] !== $expectedBundle) {
            throw ValidationException::withMessages(['file' => "This .ipa is {$meta['bundle_id']}, not {$product->name} ({$expectedBundle})."]);
        }
        if ($meta['short_version'] !== $release->version) {
            throw ValidationException::withMessages(['file' => 'This .ipa is version '.($meta['short_version'] ?? '?').", not {$release->version}."]);
        }

        $warnings = [];
        if ($release->build !== null && (string) $release->build !== (string) $meta['build']) {
            $warnings[] = "The .ipa has build {$meta['build']}; this version lists build {$release->build}.";
        }
        $profile = $meta['profile'];
        if ($profile === null) {
            $warnings[] = 'The .ipa has no provisioning profile; it cannot be installed over the air.';
        } else {
            if ($profile['type'] !== IpaInspector::PROFILE_AD_HOC) {
                $warnings[] = "The provisioning profile is {$profile['type_label']}, not Ad Hoc.";
            }
            if ($profile['expired']) {
                $warnings[] = 'The provisioning profile has expired; iPads cannot install or open this build.';
            }
        }

        return $warnings;
    }

    /**
     * A Mac version is published only with its .dmg, a Windows version
     * only with its .msi, an iPad version only with its .ipa, a shared
     * (Mac + iPad) version with both.
     */
    private function assertPublishable(LicenseRelease $release): void
    {
        if ($release->status !== LicenseRelease::STATUS_PUBLISHED) {
            return;
        }
        if ($release->needsFile() && ! $release->hasFile()) {
            throw ValidationException::withMessages(['status' => match ($release->platforms) {
                License::PLATFORM_WINDOWS => 'Upload the Windows installer (.msi) before publishing this version.',
                LicenseRelease::PLATFORM_ANDROID => 'Upload the Android app (.apk / .aab) before publishing this version.',
                default => 'Upload the Mac installer (.dmg) before publishing this version.',
            }]);
        }
        if ($release->needsIpa() && ! $release->hasIpa()) {
            throw ValidationException::withMessages(['status' => $release->platforms === LicenseRelease::PLATFORM_IOS
                ? 'Upload the iOS app (.ipa) before publishing this version.'
                : 'Upload the iPad installer (.ipa) before publishing this version.']);
        }
    }

    /**
     * @return array{0: ?LicenseProduct, 1: ?LicenseRelease}
     */
    private function find(string $product, string $release): array
    {
        $model = LicenseProduct::findByCode($product);

        return [$model, $model?->releases()->find($release)];
    }

    private function rules(LicenseProduct $product, ?LicenseRelease $release = null): array
    {
        $sometimes = $release ? ['sometimes'] : [];

        return [
            'version' => array_merge($sometimes, ['required', 'string', 'regex:'.LicenseRelease::VERSION_REGEX,
                Rule::unique('license_releases', 'version')->where('product_id', $product->id)->whereNull('deleted_at')->ignore($release?->id)]),
            'build' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'platforms' => array_merge($sometimes, ['required', Rule::in(LicenseRelease::PLATFORMS)]),
            // Multiplatform: the platforms it covers (two or more).
            'target_platforms' => ['nullable', 'required_if:platforms,'.LicenseRelease::PLATFORMS_MULTI, 'array', 'min:2'],
            'target_platforms.*' => ['distinct', Rule::in(LicenseRelease::TARGET_PLATFORMS)],
            // Android: minimum and target SDK (API level), both optional.
            'android_min_sdk' => ['nullable', 'integer', 'between:'.LicenseRelease::ANDROID_SDK_MIN.','.LicenseRelease::ANDROID_SDK_MAX],
            'android_target_sdk' => ['nullable', 'integer', 'between:'.LicenseRelease::ANDROID_SDK_MIN.','.LicenseRelease::ANDROID_SDK_MAX, 'gte:android_min_sdk'],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => array_merge($sometimes, ['required', Rule::in(LicenseRelease::STATUSES)]),
            'released_at' => ['nullable', 'required_if:status,'.LicenseRelease::STATUS_PUBLISHED, 'date_format:Y-m-d'],
            'is_critical' => ['nullable', 'boolean'],
            'download_url' => ['nullable', 'url', 'max:500'],
            'changes' => ['nullable', 'array', 'max:200'],
            'changes.*.type' => ['required', Rule::in(LicenseRelease::CHANGE_TYPES)],
            'changes.*.area' => ['nullable', 'string', 'max:100'],
            'changes.*.text' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function messages(): array
    {
        return [
            'version.regex' => 'Use a version like 1.0.0 (major.minor.patch).',
            'version.unique' => 'This version is already in the list.',
            'released_at.required_if' => 'A published version needs a release date.',
            'target_platforms.required_if' => 'Choose at least two platforms for a multiplatform version.',
            'target_platforms.min' => 'Choose at least two platforms for a multiplatform version.',
            'android_target_sdk.gte' => 'The target SDK cannot be lower than the minimum SDK.',
        ];
    }

    /**
     * Keeps only the change fields, with empty areas as null.
     */
    private function prepare(array $data, ?LicenseRelease $release = null): array
    {
        if (array_key_exists('changes', $data)) {
            $data['changes'] = array_values(array_map(fn (array $change) => [
                'type' => $change['type'],
                'area' => filled($change['area'] ?? null) ? trim($change['area']) : null,
                'text' => trim($change['text']),
            ], $data['changes'] ?? []));
        }
        if (array_key_exists('is_critical', $data)) {
            $data['is_critical'] = (bool) $data['is_critical'];
        }
        // Platform-specific fields only stay when they apply.
        $platforms = $data['platforms'] ?? $release?->platforms;
        if ($platforms !== null) {
            if ($platforms !== LicenseRelease::PLATFORMS_MULTI) {
                $data['target_platforms'] = null;
            } elseif (isset($data['target_platforms'])) {
                $data['target_platforms'] = array_values(array_intersect(LicenseRelease::TARGET_PLATFORMS, $data['target_platforms']));
            }
            $list = $platforms === LicenseRelease::PLATFORMS_MULTI
                ? ($data['target_platforms'] ?? $release?->target_platforms ?? [])
                : [$platforms];
            if (! in_array(LicenseRelease::PLATFORM_ANDROID, $list, true)) {
                $data['android_min_sdk'] = null;
                $data['android_target_sdk'] = null;
            }
        }

        return $data;
    }

    /**
     * Adds licenses_covered / licenses_needing_upgrade to each release:
     * active licenses of the platforms the release is for, split by
     * whether their updates_major reaches the release's major version.
     */
    private function withCoverage(LicenseProduct $product, $releases): void
    {
        foreach ($releases as $release) {
            $base = License::forProduct($product)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->whereIn('platform', $release->platformList());

            $release->licenses_covered = (clone $base)
                ->where(fn ($q) => $q->whereNull('updates_major')->orWhere('updates_major', '>=', $release->major))
                ->count();
            $release->licenses_needing_upgrade = (clone $base)->where('updates_major', '<', $release->major)->count();
            // Devices whose app last reported this version.
            $release->devices_on_version = LicenseActivation::query()
                ->whereHas('license', fn ($q) => $q->forProduct($product))
                ->where('app_version', $release->version)
                ->count();
            // iPad install links: still usable / already used.
            $release->install_links_active = $release->installLinks()->usable()->count();
            $release->install_links_used = $release->installLinks()->where('status', LicenseInstallLink::STATUS_USED)->count();
        }
    }

    private function notFound()
    {
        return ResponseHelper::jsonResponse(false, 'Not Found', null, 404);
    }
}
