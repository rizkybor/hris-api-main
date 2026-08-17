<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\SdmField;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SdmFieldController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            // Readable by anyone who can already touch SDM Resources (they
            // need the list for the Bidang dropdown), or who holds the
            // dedicated management permission.
            new Middleware(PermissionMiddleware::using([
                'sdm-resource-list|sdm-resource-create|sdm-resource-edit|sdm-field-menu',
            ]), only: ['index']),
            new Middleware(PermissionMiddleware::using(['sdm-field-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['sdm-field-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['sdm-field-delete']), only: ['destroy']),
        ];
    }

    public function index()
    {
        $fields = SdmField::where('is_active', true)->orderBy('name')->get();

        return ResponseHelper::jsonResponse(true, 'SDM Fields Retrieved Successfully', $fields, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sdm_fields,name'],
        ]);

        $field = SdmField::create($validated);

        return ResponseHelper::jsonResponse(true, 'Bidang SDM Created Successfully', $field, 201);
    }

    public function update(Request $request, int $id)
    {
        $field = SdmField::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:sdm_fields,name,'.$id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $field->update($validated);

        return ResponseHelper::jsonResponse(true, 'Bidang SDM Updated Successfully', $field->fresh(), 200);
    }

    public function destroy(int $id)
    {
        $field = SdmField::findOrFail($id);
        $field->delete();

        return ResponseHelper::jsonResponse(true, 'Bidang SDM Deleted Successfully', null, 200);
    }
}
