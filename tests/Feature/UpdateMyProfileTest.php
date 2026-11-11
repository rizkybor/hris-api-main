<?php

use App\Models\EmergencyContact;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Andi Pratama']);
    $this->employee = EmployeeProfile::factory()->create(['user_id' => $this->user->id, 'city' => 'Bandung']);
});

test('employee updates their own personal details and address', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/me', [
        'name' => 'Andi P.',
        'phone' => '+62 812 0000 1111',
        'place_of_birth' => 'Garut',
        'hobby' => 'Orienteering',
        'address' => 'Jl. Merdeka 10',
        'city' => 'Jakarta',
        'postal_code' => '10110',
        'additional_notes' => 'Prefers email',
    ])->assertOk();

    $this->employee->refresh();
    expect($this->user->fresh()->name)->toBe('Andi P.')
        ->and($this->employee->phone)->toBe('+62 812 0000 1111')
        ->and($this->employee->city)->toBe('Jakarta')
        ->and($this->employee->hobby)->toBe('Orienteering')
        ->and($this->employee->additional_notes)->toBe('Prefers email');
});

test('fields that are not sent stay unchanged', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/me', ['name' => 'Andi'])->assertOk();

    expect($this->employee->fresh()->city)->toBe('Bandung');
});

test('identity number and code cannot be changed through the profile', function () {
    Sanctum::actingAs($this->user);
    $original = $this->employee->only(['identity_number', 'code']);

    $this->putJson('/api/v1/me', ['name' => 'Andi', 'identity_number' => '999', 'code' => 'HACK'])->assertOk();

    expect($this->employee->fresh()->only(['identity_number', 'code']))->toBe($original);
});

test('emergency contacts are created, updated and removed as a full list', function () {
    Sanctum::actingAs($this->user);
    $keep = EmergencyContact::factory()->create(['employee_id' => $this->employee->id, 'full_name' => 'Old Name']);
    $drop = EmergencyContact::factory()->create(['employee_id' => $this->employee->id]);

    $this->putJson('/api/v1/me', [
        'name' => 'Andi',
        'emergency_contacts' => [
            ['id' => $keep->id, 'full_name' => 'New Name', 'relationship' => 'Sibling', 'phone' => '0811'],
            ['full_name' => 'Sari', 'relationship' => 'Spouse', 'phone' => '0812', 'email' => 'sari@example.com'],
        ],
    ])->assertOk();

    expect($keep->fresh()->full_name)->toBe('New Name')
        ->and(EmergencyContact::find($drop->id))->toBeNull()
        ->and(EmergencyContact::where('employee_id', $this->employee->id)->count())->toBe(2);
});

test('an empty list removes all emergency contacts', function () {
    Sanctum::actingAs($this->user);
    EmergencyContact::factory()->count(2)->create(['employee_id' => $this->employee->id]);

    $this->putJson('/api/v1/me', ['name' => 'Andi', 'emergency_contacts' => []])->assertOk();

    expect(EmergencyContact::where('employee_id', $this->employee->id)->count())->toBe(0);
});

test("an employee cannot edit another employee's emergency contact by id", function () {
    $other = EmployeeProfile::factory()->create();
    $theirs = EmergencyContact::factory()->create(['employee_id' => $other->id, 'full_name' => 'Not Yours']);
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/me', [
        'name' => 'Andi',
        'emergency_contacts' => [
            ['id' => $theirs->id, 'full_name' => 'Hijacked', 'relationship' => 'X', 'phone' => '0800'],
        ],
    ])->assertOk();

    expect($theirs->fresh()->full_name)->toBe('Not Yours')
        ->and($theirs->fresh()->employee_id)->toBe($other->id);
});

test('validation rejects a bad date of birth and an incomplete contact', function () {
    Sanctum::actingAs($this->user);

    $this->putJson('/api/v1/me', [
        'name' => 'Andi',
        'date_of_birth' => now()->addDay()->toDateString(),
        'emergency_contacts' => [['full_name' => 'Sari']],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['date_of_birth', 'emergency_contacts.0.relationship', 'emergency_contacts.0.phone']);
});
