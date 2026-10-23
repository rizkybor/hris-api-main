<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\AttendanceCheckInRequest;
use App\Http\Requests\AttendanceCheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\PaginateResource;
use App\Interfaces\AttendanceRepositoryInterface;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AttendanceController extends Controller implements HasMiddleware
{
    private AttendanceRepositoryInterface $attendanceRepository;

    public function __construct(AttendanceRepositoryInterface $attendanceRepository)
    {
        $this->attendanceRepository = $attendanceRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['attendance-list']), only: ['index', 'getAllPaginated', 'show', 'getStatistics']),
            new Middleware(PermissionMiddleware::using(['attendance-check-in']), only: ['checkIn']),
            new Middleware(PermissionMiddleware::using(['attendance-check-out']), only: ['checkOut']),
            new Middleware(PermissionMiddleware::using(['attendance-last-attendance']), only: ['getLastAttendance']),
            new Middleware(PermissionMiddleware::using(['attendance-my-attendances']), only: ['getMyAttendances', 'getMyAttendanceStatistics']),
            new Middleware(PermissionMiddleware::using(['attendance-edit']), only: ['update']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $attendances = $this->attendanceRepository->getAll(
                $request->search,
                $request->date,
                $request->limit,
                true
            );

            return ResponseHelper::jsonResponse(true, 'Attendances Retrieved Successfully', AttendanceResource::collection($attendances), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer',
            'status' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $attendances = $this->attendanceRepository->getAllPaginated(
                $request['search'] ?? null,
                $request['row_per_page'],
                $request['status'] ?? null,
                $request['start_date'] ?? null,
                $request['end_date'] ?? null
            );

            return ResponseHelper::jsonResponse(true, 'Attendances Retrieved Successfully', PaginateResource::make($attendances, AttendanceResource::class), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getMyAttendances()
    {
        try {
            $attendances = $this->attendanceRepository->getMyAttendances();

            return ResponseHelper::jsonResponse(true, 'My Attendances Retrieved Successfully', AttendanceResource::collection($attendances), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getMyAttendanceStatistics()
    {
        try {
            $statistics = $this->attendanceRepository->getMyAttendanceStatistics();

            return ResponseHelper::jsonResponse(true, 'Attendance Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function getLastAttendance()
    {
        try {
            $attendance = $this->attendanceRepository->getLastAttendanceByEmployee();

            if (! $attendance) {
                return ResponseHelper::jsonResponse(false, 'No attendance data for today', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Last Attendance Retrieved Successfully', new AttendanceResource($attendance), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function checkIn(AttendanceCheckInRequest $request)
    {
        $data = $request->validated();

        try {
            $attendance = $this->attendanceRepository->checkIn($data);

            return ResponseHelper::jsonResponse(true, 'Check-in Successful', new AttendanceResource($attendance), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function checkOut(AttendanceCheckOutRequest $request)
    {
        $data = $request->validated();

        try {
            $attendance = $this->attendanceRepository->checkOut($data);

            return ResponseHelper::jsonResponse(true, 'Check-out Successful', new AttendanceResource($attendance), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Get attendance statistics for admin
     */
    public function getStatistics()
    {
        try {
            $statistics = $this->attendanceRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Attendance Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $attendance = $this->attendanceRepository->getById($id);

            return ResponseHelper::jsonResponse(true, 'Attendance Retrieved Successfully', new AttendanceResource($attendance), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Attendance Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    // Statuses a corrected record can take; matches what check-in and the
    // attendance reports produce.
    public const EDITABLE_STATUSES = ['present', 'late', 'absent', 'overtime', 'remote', 'sick_leave'];

    /**
     * Correct one staff member's attendance record (Attendance Records ->
     * Edit). Times are entered as WIB on the record's own date and stored in
     * UTC like a normal check-in; late minutes are recalculated from the same
     * 09:00 WIB threshold check-in uses. Changes are kept in the activity log.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(self::EDITABLE_STATUSES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $attendance = Attendance::findOrFail($id);
            $day = $attendance->date->format('Y-m-d');
            $at = fn (?string $time) => $time ? Carbon::parse("{$day} {$time}", 'Asia/Jakarta') : null;
            $checkIn = $at($data['check_in'] ?? null);
            $checkOut = $at($data['check_out'] ?? null);

            if ($checkOut && ! $checkIn) {
                throw ValidationException::withMessages(['check_in' => 'Isi jam check-in jika ada jam check-out.']);
            }
            if ($checkIn && $checkOut && $checkOut->lte($checkIn)) {
                throw ValidationException::withMessages(['check_out' => 'Jam check-out harus setelah jam check-in.']);
            }
            if (! $checkIn && ! in_array($data['status'], ['absent', 'sick_leave'], true)) {
                throw ValidationException::withMessages(['check_in' => 'Jam check-in wajib diisi untuk status ini.']);
            }

            $lateMinutes = null;
            if ($data['status'] === 'late' && $checkIn) {
                $threshold = $checkIn->copy()->setTime(9, 0);
                $lateMinutes = $checkIn->gt($threshold) ? (int) $threshold->diffInMinutes($checkIn) : null;
            }

            $attendance->update([
                'check_in' => $checkIn?->copy()->utc(),
                'check_out' => $checkOut?->copy()->utc(),
                'status' => $data['status'],
                'late_minutes' => $lateMinutes,
                'notes' => $data['notes'] ?? null,
            ]);

            return ResponseHelper::jsonResponse(true, 'Attendance Updated Successfully',
                new AttendanceResource($attendance->fresh(['employee.user', 'employee.jobInformation.team'])), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Attendance Not Found', null, 404);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }
}
