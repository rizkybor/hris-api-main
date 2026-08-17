<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\ConfigurableOption;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ConfigurableOptionController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['option-menu|option-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['option-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['option-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['option-delete']), only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $request->validate([
                'category' => ['required', 'string', Rule::in(ConfigurableOption::CATEGORIES)],
            ]);

            $options = ConfigurableOption::category($request->input('category'))
                ->ordered()
                ->get();

            return ResponseHelper::jsonResponse(true, 'Options Retrieved Successfully', $options, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'category' => ['required', 'string', Rule::in(ConfigurableOption::CATEGORIES)],
                'value' => ['required', 'string', 'max:100'],
                'label' => ['required', 'string', 'max:255'],
            ]);

            $exists = ConfigurableOption::category($validated['category'])
                ->where('value', $validated['value'])
                ->exists();

            if ($exists) {
                return ResponseHelper::jsonResponse(false, 'This value already exists in this category', null, 422);
            }

            $nextOrder = ConfigurableOption::category($validated['category'])->max('sort_order') + 1;

            $option = ConfigurableOption::create([
                'category' => $validated['category'],
                'value' => $validated['value'],
                'label' => $validated['label'],
                'sort_order' => $nextOrder,
                'is_active' => true,
            ]);

            return ResponseHelper::jsonResponse(true, 'Option Created Successfully', $option, 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $option = ConfigurableOption::findOrFail($id);

            $validated = $request->validate([
                'value' => ['sometimes', 'required', 'string', 'max:100'],
                'label' => ['sometimes', 'required', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ]);

            if (isset($validated['value']) && $validated['value'] !== $option->value) {
                $exists = ConfigurableOption::category($option->category)
                    ->where('value', $validated['value'])
                    ->where('id', '!=', $option->id)
                    ->exists();

                if ($exists) {
                    return ResponseHelper::jsonResponse(false, 'This value already exists in this category', null, 422);
                }
            }

            $option->update($validated);

            return ResponseHelper::jsonResponse(true, 'Option Updated Successfully', $option->fresh(), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Option Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $option = ConfigurableOption::findOrFail($id);
            $option->delete();

            return ResponseHelper::jsonResponse(true, 'Option Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Option Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
