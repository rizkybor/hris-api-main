<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ScreeningResource;
use App\Models\Screening;
use App\Models\ServiceItem;
use App\Services\ScreeningForm;
use App\Services\ScreeningRecommender;
use App\Services\ScreeningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Client Screening in HRIS: the list, filling a screening in for a client,
 * invite links, the recommended items (with internal prices) and the
 * "Ringkasan Kebutuhan" PDF.
 */
class ScreeningController extends Controller implements HasMiddleware
{
    public function __construct(
        private ScreeningService $service,
        private ScreeningRecommender $recommender,
        private ScreeningForm $form,
    ) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['screening-menu|screening-list']), only: ['index', 'show', 'pdf']),
            new Middleware(PermissionMiddleware::using(['screening-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['screening-edit']), only: ['update', 'recalculate', 'saveItems']),
            new Middleware(PermissionMiddleware::using(['screening-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['screening-share']), only: ['invite']),
        ];
    }

    private function detail(Screening $screening): ScreeningResource
    {
        return (new ScreeningResource($screening->load(['client', 'creator'])))->withDetail();
    }

    public function index(Request $request)
    {
        $query = Screening::query()->with(['client', 'creator'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($search = trim((string) $request->search)) {
            $query->where(fn ($q) => $q->where('code', 'like', "%$search%")
                ->orWhere('contact_name', 'like', "%$search%")
                ->orWhere('company', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"));
        }

        $page = $query->paginate((int) ($request->row_per_page ?: 15));

        return ResponseHelper::jsonResponse(true, 'Screenings Retrieved Successfully', [
            ...PaginateResource::make($page, ScreeningResource::class)->resolve(),
            'status_counts' => Screening::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['internal', 'invite'])],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'lang' => ['nullable', Rule::in(['id', 'en'])],
        ]);

        $invite = $data['mode'] === 'invite';
        $screening = $this->service->create([
            ...collect($data)->except('mode')->all(),
            'lang' => $data['lang'] ?? 'id',
            'source' => $invite ? 'invite' : 'internal',
            'status' => $invite ? 'invited' : 'draft',
            'token_expires_at' => $invite ? now()->addDays(config('services.screening.invite_days')) : null,
            'answers' => [],
            'created_by' => $request->user()?->id,
        ]);

        return ResponseHelper::jsonResponse(true, 'Screening Created Successfully', $this->detail($screening), 201);
    }

    public function show(int $id)
    {
        $screening = Screening::find($id);

        return $screening
            ? ResponseHelper::jsonResponse(true, 'Screening Retrieved Successfully', $this->detail($screening), 200)
            : ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
    }

    public function update(Request $request, int $id)
    {
        $screening = Screening::find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }

        $data = $request->validate([
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'company' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'status' => ['sometimes', Rule::in(Screening::STATUSES)],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'answers' => ['sometimes', 'array'],
            'submit' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('answers', $data)) {
            $data['answers'] = $this->form->clean($screening->template_snapshot ?? [], $data['answers']);
        }
        $submit = (bool) ($data['submit'] ?? false);
        unset($data['submit']);

        if ($submit) {
            $missing = $this->form->missing($screening->template_snapshot ?? [], $data['answers'] ?? $screening->answers ?? []);
            if ($missing) {
                return ResponseHelper::jsonResponse(false, 'Masih ada pertanyaan wajib yang belum diisi.', collect($missing)->mapWithKeys(fn ($l, $k) => ["answers.$k" => ["Mohon isi: $l"]]), 422);
            }
        }

        $screening->update($data);

        // Filled in by the team: analyse it straight away.
        if ($submit) {
            $screening->update([
                'status' => in_array($screening->status, ['draft', 'invited', 'new'], true) ? 'in_review' : $screening->status,
                'submitted_at' => $screening->submitted_at ?? now(),
            ]);
            $this->recommender->apply($screening);
        }

        return ResponseHelper::jsonResponse(true, 'Screening Updated Successfully', $this->detail($screening->fresh()), 200);
    }

    public function destroy(int $id)
    {
        $screening = Screening::find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }
        $screening->delete();

        return ResponseHelper::jsonResponse(true, 'Screening Deleted Successfully', null, 200);
    }

    /** Rebuild the auto recommendations from the answers (manual items stay). */
    public function recalculate(int $id)
    {
        $screening = Screening::find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }
        $this->recommender->apply($screening);

        return ResponseHelper::jsonResponse(true, 'Recommendations Updated', $this->detail($screening->fresh()), 200);
    }

    /** Replace the item list with the edited one (new rows are manual). */
    public function saveItems(Request $request, int $id)
    {
        $screening = Screening::find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }

        $data = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.service_item_id' => ['nullable', 'integer', 'exists:service_items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category' => ['nullable', 'string', 'max:60'],
            'items.*.qty' => ['required', 'numeric', 'min:0', 'max:9999'],
            'items.*.hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.price_unit' => ['required', Rule::in(ServiceItem::PRICE_UNITS)],
            'items.*.duration_text' => ['nullable', 'string', 'max:100'],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
            'items.*.is_selected' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($screening, $data) {
            $existing = $screening->items()->get()->keyBy('id');
            $keep = [];
            foreach ($data['items'] as $i => $row) {
                $fields = collect($row)->except('id')->all() + ['sort_order' => ($i + 1) * 10];
                $item = isset($row['id']) ? $existing->get($row['id']) : null;
                if ($item) {
                    $item->update($fields);
                } else {
                    $item = $screening->items()->create($fields + ['source' => 'manual']);
                }
                $keep[] = $item->id;
            }
            $screening->items()->whereNotIn('id', $keep)->delete();
        });

        return ResponseHelper::jsonResponse(true, 'Items Saved', $this->detail($screening->fresh()), 200);
    }

    /** New (or renewed) link for the client to fill the form themselves. */
    public function invite(Request $request, int $id)
    {
        $screening = Screening::find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }
        if ($screening->submitted_at) {
            return ResponseHelper::jsonResponse(false, 'Screening ini sudah dikirim, link undangan tidak bisa dibuat lagi.', null, 422);
        }

        $lang = $request->input('lang');
        $screening->update([
            'token' => Str::random(48),
            'token_expires_at' => now()->addDays(config('services.screening.invite_days')),
            'source' => 'invite',
            'status' => 'invited',
            'lang' => in_array($lang, ['id', 'en'], true) ? $lang : $screening->lang,
        ]);

        return ResponseHelper::jsonResponse(true, 'Invite Link Created', $this->detail($screening->fresh()), 200);
    }

    /** "Ringkasan Kebutuhan" PDF; prices only with ?prices=1. */
    public function pdf(Request $request, int $id)
    {
        $screening = Screening::with('items.serviceItem')->find($id);
        if (! $screening) {
            return ResponseHelper::jsonResponse(false, 'Screening Not Found', null, 404);
        }

        $withPrices = $request->boolean('prices');
        $pdf = Pdf::loadView('pdf.screening-summary', [
            'screening' => $screening,
            'sections' => $this->form->readable($screening),
            'items' => $screening->items->where('is_selected', true)->values(),
            'estimate' => $this->recommender->estimate($screening),
            'withPrices' => $withPrices,
        ])->setPaper('a4');

        $name = Str::slug($screening->company ?: $screening->contact_name ?: 'klien');

        return $pdf->download("Ringkasan-Kebutuhan-{$screening->code}-{$name}".($withPrices ? '-internal' : '').'.pdf');
    }
}
