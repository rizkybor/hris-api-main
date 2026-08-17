<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\LetterStoreRequest;
use App\Http\Requests\LetterUpdateRequest;
use App\Models\DivisionCode;
use App\Models\Letter;
use App\Models\LetterCode;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class LetterController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['letter-menu|letter-list']), only: ['index', 'show', 'exportPdf']),
            new Middleware(PermissionMiddleware::using(['letter-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['letter-edit']), only: ['update', 'cancel']),
            new Middleware(PermissionMiddleware::using(['letter-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Letter::query()->with(['letterCode', 'divisionCode', 'creator:id,name', 'employee.user'])->orderByDesc('created_at');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->employee_id) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            $letters = $query->paginate($rowPerPage);

            return ResponseHelper::jsonResponse(true, 'Letters Retrieved Successfully', $letters, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(LetterStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $date = Carbon::parse($validated['date']);
            $letterCode = LetterCode::findOrFail($validated['letter_code_id']);
            $divisionCode = DivisionCode::findOrFail($validated['division_code_id']);

            $generated = $this->numberService->generateLetterNumber(
                $letterCode->code,
                $validated['type'],
                $divisionCode->code,
                $date
            );

            $letter = Letter::create([
                ...$validated,
                'letter_number' => $generated['number'],
                'sequence' => $generated['sequence'],
                'year' => $generated['year'],
                'created_by' => Auth::id(),
            ]);

            return ResponseHelper::jsonResponse(true, 'Letter Created Successfully', $letter->load(['letterCode', 'divisionCode']), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function show(string $id)
    {
        try {
            $letter = Letter::with(['letterCode', 'divisionCode', 'creator:id,name'])->findOrFail($id);

            return ResponseHelper::jsonResponse(true, 'Letter Retrieved Successfully', $letter, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(LetterUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $letter = Letter::findOrFail($id);
            $letter->update($validated);

            return ResponseHelper::jsonResponse(true, 'Letter Updated Successfully', $letter->load(['letterCode', 'divisionCode']), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Cancelled letters keep their number reserved and are labeled "DIBATALKAN" —
     * they are never deleted or renumbered, per company numbering policy.
     */
    public function cancel(string $id)
    {
        try {
            $letter = Letter::findOrFail($id);
            $letter->update(['status' => 'cancelled']);

            return ResponseHelper::jsonResponse(true, 'Letter Cancelled Successfully', $letter, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $letter = Letter::findOrFail($id);
            $letter->delete();

            return ResponseHelper::jsonResponse(true, 'Letter Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Letter Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function exportPdf(string $id)
    {
        $letter = Letter::with(['letterCode', 'divisionCode'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.letter', ['letter' => $letter])
            ->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $letter->letter_number).'.pdf');
    }
}
