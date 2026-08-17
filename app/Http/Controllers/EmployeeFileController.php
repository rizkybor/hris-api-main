<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\EmployeeFileResource;
use App\Models\EmployeeFile;
use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\PermissionMiddleware;

class EmployeeFileController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['employee-list|employee-create|employee-edit|employee-delete']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['employee-edit']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['employee-delete']), only: ['destroy']),
        ];
    }

    public function index(string $employeeId)
    {
        try {
            $employee = EmployeeProfile::findOrFail($employeeId);

            $files = $employee->files()->with('uploader')->get();

            return ResponseHelper::jsonResponse(true, 'Employee Files Retrieved Successfully', EmployeeFileResource::collection($files), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(Request $request, string $employeeId)
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
            'names' => ['required', 'array', 'size:'.count($request->file('files', []))],
            'names.*' => ['required', 'string', 'max:255'],
        ]);

        try {
            $employee = EmployeeProfile::findOrFail($employeeId);
            $names = $request->input('names', []);

            $uploaded = collect($request->file('files'))->map(function ($file, $index) use ($employee, $names) {
                $storedPath = $file->store('employee-files', 'public');

                return $employee->files()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'display_name' => $names[$index] ?? $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType(),
                    'size_file' => $this->formatBytes($file->getSize()),
                    'uploaded_by' => Auth::id(),
                ]);
            });

            $uploaded->each->load('uploader');

            return ResponseHelper::jsonResponse(true, 'File(s) Uploaded Successfully', EmployeeFileResource::collection($uploaded), 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Employee Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $file = EmployeeFile::findOrFail($id);

            if ($file->file_path && Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }

            $file->delete();

            return ResponseHelper::jsonResponse(true, 'File Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'File Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
