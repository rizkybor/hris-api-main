<?php

use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffTask;
use App\Models\User;
use App\Services\CertificateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->creator = User::factory()->create();
    $this->employee = EmployeeProfile::factory()->create(['user_id' => User::factory()->create()->id]);
    $this->other = EmployeeProfile::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->staffTask = function (string $title, string $status, ?string $at, ?int $employeeId = null) {
        $task = StaffTask::create(['title' => $title, 'due_date' => '2026-12-31', 'assignment_mode' => 'selected', 'created_by' => $this->creator->id]);
        $task->assignees()->create([
            'employee_id' => $employeeId ?? $this->employee->id,
            'status' => $status,
            'status_updated_at' => $at,
        ]);
    };

    $this->project = Project::create(['name' => 'HRIS Redesign', 'start_date' => '2026-01-01']);
    $this->projectTask = function (string $name, string $status, string $updatedAt, bool $viaPivot = true) {
        $task = ProjectTask::create([
            'project_id' => $this->project->id,
            'name' => $name,
            'status' => $status,
            'assignee_id' => $viaPivot ? null : $this->employee->id,
        ]);
        if ($viaPivot) {
            $task->assignees()->attach($this->employee->id);
        }
        DB::table('project_tasks')->where('id', $task->id)->update(['updated_at' => $updatedAt]);
    };
});

test('lists the employee\'s finished staff and project tasks, newest first', function () {
    ($this->staffTask)('Staff done', 'done', '2026-10-05 10:00:00');
    ($this->staffTask)('Staff still open', 'in_progress', '2026-10-06 10:00:00');
    ($this->staffTask)('Someone else\'s', 'done', '2026-10-07 10:00:00', $this->other->id);
    ($this->projectTask)('Project done', 'done', '2026-10-09 08:00:00');
    ($this->projectTask)('Project legacy assignee', 'done', '2026-10-01 08:00:00', false);
    ($this->projectTask)('Project in review', 'in_review', '2026-10-10 08:00:00');

    $tasks = app(CertificateService::class)->completedTasks($this->employee->id);

    expect(array_column($tasks, 'title'))->toBe(['Project done', 'Staff done', 'Project legacy assignee'])
        ->and($tasks[0]['context'])->toBe('HRIS Redesign')
        ->and($tasks[1]['context'])->toBe('Staff Task')
        ->and($tasks[1]['completed_at'])->toBeInstanceOf(Carbon::class);
});

test('keeps only tasks finished inside the certificate period', function () {
    // Stored in UTC; the period is in WIB (UTC+7).
    ($this->staffTask)('Before', 'done', '2026-08-31 16:30:00'); // 31 Aug 23:30 WIB
    ($this->staffTask)('First day', 'done', '2026-08-31 17:30:00'); // 1 Sep 00:30 WIB
    ($this->projectTask)('Last day', 'done', '2026-09-30 16:00:00'); // 30 Sep 23:00 WIB
    ($this->projectTask)('After', 'done', '2026-09-30 17:30:00'); // 1 Oct 00:30 WIB

    $tasks = app(CertificateService::class)->completedTasks($this->employee->id, '2026-09-01', '2026-09-30');

    expect(array_column($tasks, 'title'))->toBe(['Last day', 'First day']);
});

test('the certificate page renders portrait with the task appendix', function () {
    ($this->staffTask)('Rancang halaman laporan', 'done', '2026-10-05 10:00:00');
    $certificate = new App\Models\Certificate([
        'certificate_number' => '001/JCD/A/B/X/2026',
        'title' => 'Magang',
        'recipient_name' => 'Ayu',
        'signatory_name' => 'Budi',
        'signatory_title' => 'Director',
        'orientation' => 'portrait',
        'include_tasks' => true,
    ]);

    $html = view('pdf.certificate', [
        'certificate' => $certificate,
        'backgroundImagePath' => public_path('images/certificate-default-bg-portrait.jpg'),
        'companyName' => 'PT',
        'generatedAt' => now(),
        'portrait' => true,
        'tasks' => app(CertificateService::class)->completedTasks($this->employee->id),
    ])->render();

    expect($html)->toContain('size: a4 portrait')
        ->toContain('Task yang Telah Diselesaikan')
        ->toContain('Rancang halaman laporan')
        ->toContain('05 Oct 2026, 17:00');
});

test('the generate request accepts the layout, the task page and an employee per recipient', function () {
    $rules = (new App\Http\Requests\CertificateGenerateRequest)->rules();

    expect($rules)->toHaveKeys(['orientation', 'include_tasks', 'recipients.*.employee_id'])
        ->and($rules['orientation'])->toContain('in:landscape,portrait');
});

test('the recipient picker lists employees, including ended accounts, to certificate creators only', function () {
    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'certificate-create', 'guard_name' => 'sanctum']);
    $this->employee->user->forceFill(['is_active' => false])->save();
    $creator = User::factory()->create();
    $creator->givePermissionTo('certificate-create');

    Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/certificates/employee-options')->assertForbidden();

    Laravel\Sanctum\Sanctum::actingAs($creator);
    $rows = collect($this->getJson('/api/v1/certificates/employee-options')->assertOk()->json('data'));

    expect($rows->firstWhere('id', $this->employee->id))->toMatchArray(['name' => $this->employee->user->name, 'is_active' => false])
        ->and($rows->firstWhere('id', $this->other->id)['is_active'])->toBeTrue();
});
