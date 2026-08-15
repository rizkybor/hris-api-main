<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DivisionCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class DivisionCodeController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['letter-menu|letter-list|letter-create']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['letter-create', 'letter-edit']), only: ['store', 'update']),
            new Middleware(PermissionMiddleware::using(['letter-delete']), only: ['destroy']),
        ];
    }

    public function index()
    {
        $codes = DivisionCode::orderBy('code')->get();

        return ResponseHelper::jsonResponse(true, 'Division Codes Retrieved Successfully', $codes, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:division_codes,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $code = DivisionCode::create($validated);

        return ResponseHelper::jsonResponse(true, 'Division Code Created Successfully', $code, 201);
    }

    public function update(Request $request, string $id)
    {
        $code = DivisionCode::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', 'unique:division_codes,code,'.$code->id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $code->update($validated);

        return ResponseHelper::jsonResponse(true, 'Division Code Updated Successfully', $code, 200);
    }

    public function destroy(string $id)
    {
        $code = DivisionCode::findOrFail($id);
        $code->delete();

        return ResponseHelper::jsonResponse(true, 'Division Code Deleted Successfully', null, 200);
    }
}
