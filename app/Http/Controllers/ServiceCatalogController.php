<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\ProjectRateSetting;
use App\Models\ScreeningTemplate;
use App\Models\ServiceItem;
use App\Services\RateCardSyncService;
use App\Support\ScreeningDefaults;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Settings > Client Screening: the service catalog (synced from the Rate
 * Card) and the questionnaire template.
 */
class ServiceCatalogController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            // Reading the catalog is needed to add items to a screening.
            new Middleware(PermissionMiddleware::using(['screening-edit|screening-settings']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['screening-settings']), except: ['index']),
        ];
    }

    public function index()
    {
        return ResponseHelper::jsonResponse(true, 'Service Catalog Retrieved Successfully', [
            'items' => ServiceItem::orderBy('sort_order')->orderBy('name')->get(),
            'hourly_rate' => (float) (ProjectRateSetting::first()?->rate_sell_per_hour ?? 0),
            'last_synced_at' => ServiceItem::max('synced_at'),
        ], 200);
    }

    private function rules(?ServiceItem $item = null): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('service_items', 'code')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pricing_mode' => ['required', Rule::in(ServiceItem::PRICING_MODES)],
            'price_from' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['required', Rule::in(ServiceItem::PRICE_UNITS)],
            'default_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'duration_text' => ['nullable', 'string', 'max:100'],
            'availability' => ['required', Rule::in(['available', 'preparation'])],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function store(Request $request)
    {
        $item = ServiceItem::create($request->validate($this->rules()));

        return ResponseHelper::jsonResponse(true, 'Service Item Created', $item, 201);
    }

    public function update(Request $request, int $id)
    {
        $item = ServiceItem::find($id);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'Service Item Not Found', null, 404);
        }
        $item->update($request->validate($this->rules($item)));

        return ResponseHelper::jsonResponse(true, 'Service Item Updated', $item, 200);
    }

    public function destroy(int $id)
    {
        $item = ServiceItem::find($id);
        if (! $item) {
            return ResponseHelper::jsonResponse(false, 'Service Item Not Found', null, 404);
        }
        // Screenings keep their copied rows; only the link is cleared.
        $item->delete();

        return ResponseHelper::jsonResponse(true, 'Service Item Deleted', null, 200);
    }

    public function sync(RateCardSyncService $sync)
    {
        try {
            $result = $sync->sync();
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Gagal membaca Rate Card dari Notion: '.$e->getMessage(), null, 502);
        }

        return ResponseHelper::jsonResponse(true, "Rate Card tersinkron: {$result['updated']} diperbarui, {$result['created']} baru.", $result, 200);
    }

    public function template()
    {
        $template = ScreeningTemplate::default();

        return ResponseHelper::jsonResponse(true, 'Screening Template Retrieved Successfully', [
            'template' => $template,
            'item_codes' => ServiceItem::orderBy('sort_order')->pluck('name', 'code'),
            'system_types' => ScreeningDefaults::SYSTEM_TYPES,
        ], 200);
    }

    /**
     * Save the edited questionnaire. Existing screenings keep the copy they
     * were answered on; new ones use this version.
     */
    public function updateTemplate(Request $request)
    {
        $data = $request->validate([
            'definition' => ['required', 'array'],
            'definition.sections' => ['required', 'array', 'min:1', 'max:30'],
            'definition.sections.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'definition.sections.*.title.id' => ['required', 'string', 'max:150'],
            'definition.sections.*.title.en' => ['required', 'string', 'max:150'],
            'definition.sections.*.questions' => ['required', 'array', 'min:1', 'max:40'],
            'definition.sections.*.questions.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'definition.sections.*.questions.*.type' => ['required', Rule::in(['single', 'multi', 'boolean', 'text', 'textarea', 'number'])],
            'definition.sections.*.questions.*.label.id' => ['required', 'string', 'max:300'],
            'definition.sections.*.questions.*.label.en' => ['required', 'string', 'max:300'],
            'definition.sections.*.questions.*.options' => ['nullable', 'array', 'max:40'],
            'definition.sections.*.questions.*.options.*.value' => ['required', 'string', 'max:40'],
            'definition.sections.*.questions.*.options.*.label.id' => ['required', 'string', 'max:200'],
            'definition.sections.*.questions.*.options.*.label.en' => ['required', 'string', 'max:200'],
        ]);

        $keys = collect($data['definition']['sections'])->flatMap(fn ($s) => collect($s['questions'])->pluck('key'));
        if ($keys->duplicates()->isNotEmpty()) {
            return ResponseHelper::jsonResponse(false, 'Kunci pertanyaan harus unik: '.$keys->duplicates()->unique()->implode(', '), null, 422);
        }
        if (! $keys->contains('needs')) {
            return ResponseHelper::jsonResponse(false, 'Pertanyaan "needs" (Apa yang Anda butuhkan?) tidak boleh dihapus.', null, 422);
        }

        // Validated above for shape; the full submitted definition is kept
        // so option effects (add/replace/points/...) survive.
        $definition = $request->input('definition');
        $template = ScreeningTemplate::default();
        $template
            ? $template->update(['definition' => $definition, 'updated_by' => $request->user()->id])
            : $template = ScreeningTemplate::create(['name' => 'Default', 'definition' => $definition, 'is_default' => true, 'updated_by' => $request->user()->id]);

        return ResponseHelper::jsonResponse(true, 'Template Saved', $template->fresh(), 200);
    }

    /** Back to the built-in questionnaire. */
    public function resetTemplate(Request $request)
    {
        $template = ScreeningTemplate::default();
        $attrs = ['definition' => ScreeningDefaults::template(), 'updated_by' => $request->user()->id];
        $template ? $template->update($attrs) : $template = ScreeningTemplate::create($attrs + ['name' => 'Default', 'is_default' => true]);

        return ResponseHelper::jsonResponse(true, 'Template Reset', $template->fresh(), 200);
    }
}
