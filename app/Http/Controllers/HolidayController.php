<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * Holidays managed from Attendance Settings. Reading is open to any signed-in
 * user (like the attendance setting itself); changes need attendance-setting-edit.
 */
class HolidayController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-setting-edit']), only: ['store', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $year = (int) ($request->query('year') ?: now('Asia/Jakarta')->year);

        $holidays = Holiday::whereYear('date', $year)->orderBy('date')->get()
            ->map(fn ($h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name]);

        return ResponseHelper::jsonResponse(true, 'Holidays Retrieved Successfully', $holidays, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if (Holiday::whereDate('date', $value)->exists()) {
                    $fail('Tanggal ini sudah terdaftar sebagai hari libur.');
                }
            }],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $holiday = Holiday::create([...$validated, 'created_by' => $request->user()?->id]);

        return ResponseHelper::jsonResponse(true, 'Holiday Added Successfully', ['id' => $holiday->id, 'date' => $holiday->date->toDateString(), 'name' => $holiday->name], 201);
    }

    public function destroy(int $id)
    {
        $holiday = Holiday::find($id);
        if (! $holiday) {
            return ResponseHelper::jsonResponse(false, 'Holiday Not Found', null, 404);
        }
        $holiday->delete();

        return ResponseHelper::jsonResponse(true, 'Holiday Deleted Successfully', null, 200);
    }
}
