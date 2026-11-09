<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\LicenseProductResource;
use App\Models\LicenseProduct;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Products (apps) of the License Manager. {product} is the product code.
 * Products use the License Manager permissions: reading needs
 * license-manager-menu or -list (the sidebar and the per-product pages need
 * the product list); adding needs -create, editing (incl. icon, terms,
 * on / off) needs -edit, deleting needs -delete.
 */
class LicenseProductController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['license-manager-menu|license-manager-list']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['license-manager-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['license-manager-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['license-manager-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['active' => 'nullable|boolean']);

        $products = LicenseProduct::query()
            ->withCount(['licenses' => fn ($query) => $query->withTrashed()])
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('id')
            ->get();

        return ResponseHelper::jsonResponse(true, 'License Products Retrieved Successfully', LicenseProductResource::collection($products), 200);
    }

    public function show(string $product)
    {
        $model = LicenseProduct::query()->withCount(['licenses' => fn ($query) => $query->withTrashed()])->where('code', $product)->first();
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'Product Not Found', null, 404);
        }

        return ResponseHelper::jsonResponse(true, 'License Product Retrieved Successfully', new LicenseProductResource($model), 200);
    }

    public function store(Request $request)
    {
        $request->merge([
            'code' => strtolower(trim((string) $request->input('code'))),
            'key_prefix' => strtoupper(trim((string) $request->input('key_prefix'))),
        ]);

        $data = $request->validate(array_merge($this->rules(), [
            'code' => ['required', 'string', 'min:2', 'max:50', 'regex:'.LicenseProduct::CODE_REGEX, Rule::unique('license_products', 'code')],
            'terms_version' => ['nullable', 'required_with:terms', 'string', 'max:20', 'regex:'.self::VERSION_REGEX],
        ]), $this->messages());

        $icon = $data['icon'] ?? null;
        unset($data['icon'], $data['remove_icon']);
        if (filled($data['terms'] ?? null) && empty($data['terms_effective_at'])) {
            $data['terms_effective_at'] = now()->toDateString();
        }

        $product = LicenseProduct::create($data + ['is_active' => true]);
        if ($icon) {
            $this->replaceIcon($product, $icon);
        }

        return ResponseHelper::jsonResponse(true, 'License Product Created Successfully', new LicenseProductResource($product->loadCount('licenses')), 201);
    }

    /**
     * The code is part of the public API path compiled into the apps, so it
     * never changes; name, key prefix (new keys only), description and
     * is_active can.
     */
    public function update(Request $request, string $product)
    {
        $model = LicenseProduct::query()->where('code', $product)->first();
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'Product Not Found', null, 404);
        }

        if ($request->has('key_prefix')) {
            $request->merge(['key_prefix' => strtoupper(trim((string) $request->input('key_prefix')))]);
        }

        $rules = $this->rules();
        foreach (['name', 'key_prefix'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }
        $termsChanged = $request->has('terms') && self::normalizeTerms($request->input('terms')) !== self::normalizeTerms($model->terms);
        // New terms text needs a new version (licenses keep the version
        // they were issued under).
        $rules['terms_version'] = $termsChanged && filled($request->input('terms'))
            ? ['required', 'string', 'max:20', 'regex:'.self::VERSION_REGEX, Rule::notIn(array_filter([$model->terms_version]))]
            : ['sometimes', 'nullable', 'string', 'max:20', 'regex:'.self::VERSION_REGEX];
        $data = $request->validate($rules, $this->messages() + [
            'terms_version.not_in' => 'The terms text changed: give it a new version (the current one is '.$model->terms_version.').',
        ]);
        $icon = $data['icon'] ?? null;
        $removeIcon = (bool) ($data['remove_icon'] ?? false);
        unset($data['icon'], $data['remove_icon']);
        if ($termsChanged && filled($data['terms'] ?? null) && empty($data['terms_effective_at'])) {
            $data['terms_effective_at'] = now()->toDateString();
        }

        $model->update($data);

        if ($icon) {
            $this->replaceIcon($model, $icon);
        } elseif ($removeIcon && $model->icon_path) {
            Storage::disk(LicenseProduct::ICON_DISK)->delete($model->icon_path);
            $model->update(['icon_path' => null]);
        }

        return ResponseHelper::jsonResponse(true, 'License Product Updated Successfully', new LicenseProductResource($model->loadCount(['licenses' => fn ($query) => $query->withTrashed()])), 200);
    }

    /**
     * Only a product without any license (deleted ones included) can be
     * deleted.
     */
    public function destroy(string $product)
    {
        $model = LicenseProduct::query()->where('code', $product)->first();
        if (! $model) {
            return ResponseHelper::jsonResponse(false, 'Product Not Found', null, 404);
        }

        if ($model->licenses()->withTrashed()->exists()) {
            return ResponseHelper::jsonResponse(false, 'This product still has licenses and cannot be deleted. Switch it off instead.', null, 422);
        }

        if ($model->icon_path) {
            Storage::disk(LicenseProduct::ICON_DISK)->delete($model->icon_path);
        }
        $model->delete();

        return ResponseHelper::jsonResponse(true, 'License Product Deleted Successfully', null, 200);
    }

    /**
     * Store the new icon on the public disk (license-products/), then delete
     * the old file. A unique name keeps browsers from showing a cached icon.
     */
    private function replaceIcon(LicenseProduct $product, UploadedFile $icon): void
    {
        $old = $product->icon_path;
        $path = $icon->storeAs(
            LicenseProduct::ICON_DIRECTORY,
            $product->code.'-'.now()->format('YmdHis').'-'.substr(md5((string) microtime(true)), 0, 6).'.'.$icon->extension(),
            LicenseProduct::ICON_DISK
        );
        $product->update(['icon_path' => $path]);

        if ($old && $old !== $path) {
            Storage::disk(LicenseProduct::ICON_DISK)->delete($old);
        }
    }

    /**
     * "1.0", "1.1", "2" ...
     */
    private const VERSION_REGEX = '/^[0-9]+(\.[0-9]+){0,2}$/';

    private static function normalizeTerms(?string $terms): string
    {
        return trim(str_replace("\r\n", "\n", (string) $terms));
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key_prefix' => ['required', 'string', 'regex:'.LicenseProduct::KEY_PREFIX_REGEX],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            // App icon: png / jpg / webp up to 1 MB; square works best.
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_icon' => ['nullable', 'boolean'],
            // License terms (Markdown) for the certificate appendix.
            'terms' => ['nullable', 'string', 'max:200000'],
            'terms_effective_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function messages(): array
    {
        return [
            'code.regex' => 'The code may only contain lowercase letters, digits and single hyphens (e.g. map-tools-2).',
            'key_prefix.regex' => 'The key prefix must be 1-6 uppercase letters (e.g. OC).',
            'icon.max' => 'The icon may not be larger than 1 MB.',
            'icon.mimes' => 'The icon must be a PNG, JPG or WebP image.',
        ];
    }
}
