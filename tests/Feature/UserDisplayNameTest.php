<?php

use App\Models\DocumentSetting;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\PersonName;
use App\Support\SignatoryName;
use App\Support\SignatureImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    PersonName::flush();
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
});

test('names show without titles and degrees by default', function () {
    $user = User::factory()->create(['name' => 'Dr. Rizky Ajie Kurniawan, S.Kom.']);

    expect($user->fresh()->name)->toBe('Rizky Ajie Kurniawan')
        ->and($user->fresh()->full_name)->toBe('Dr. Rizky Ajie Kurniawan, S.Kom.')
        ->and($user->fresh()->show_degree)->toBeFalse();
});

test('show_degree shows the full name, also when only id and name are selected', function () {
    $user = User::factory()->create(['name' => 'Andy Saputra, S.T.']);
    $user->update(['show_degree' => true]);
    PersonName::flush();

    expect($user->fresh()->name)->toBe('Andy Saputra, S.T.')
        ->and(User::query()->select('id', 'name')->find($user->id)->name)->toBe('Andy Saputra, S.T.');
});

test('saving a name without degrees keeps the existing ones; full_name sets it exactly', function () {
    $user = User::factory()->create(['name' => 'Aldi PP, S.Ikom']);

    $user->update(['name' => 'Aldi Pratama Putra']);
    expect($user->fresh()->full_name)->toBe('Aldi Pratama Putra, S.Ikom');

    $user->update(['name' => 'Aldi Pratama Putra, M.Kom.']);
    expect($user->fresh()->full_name)->toBe('Aldi Pratama Putra, M.Kom.');

    $user->update(['full_name' => 'Aldi Pratama Putra']);
    expect($user->fresh()->full_name)->toBe('Aldi Pratama Putra');
});

test('my profile edits the full name and the show-degree choice', function () {
    $user = User::factory()->create(['name' => 'Andi Pratama']);
    EmployeeProfile::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/me', ['name' => 'Andi Pratama, S.E.', 'show_degree' => true])->assertOk();
    PersonName::flush();

    $me = $this->getJson('/api/v1/me')->assertOk()->json('data');
    expect($me['name'])->toBe('Andi Pratama, S.E.')
        ->and($me['full_name'])->toBe('Andi Pratama, S.E.')
        ->and($me['show_degree'])->toBeTrue();

    $this->putJson('/api/v1/me', ['name' => 'Andi Pratama, S.E.', 'show_degree' => false])->assertOk();
    PersonName::flush();
    expect($this->getJson('/api/v1/me')->json('data.name'))->toBe('Andi Pratama');
});

test('signatory names of HRIS users follow their own setting; others follow the document setting', function () {
    User::factory()->create(['name' => 'Aldi PP, S.Ikom']); // degree off (default)
    $andy = User::factory()->create(['name' => 'Andy Saputra, S.T.']);
    $andy->update(['show_degree' => true]);
    PersonName::flush();
    DocumentSetting::current()->update(['signatory_show_degree' => true]);

    expect(SignatoryName::format('Aldi PP, S.Ikom'))->toBe('Aldi PP')
        ->and(SignatoryName::format('Aldi PP'))->toBe('Aldi PP')
        ->and(SignatoryName::format('Andy Saputra'))->toBe('Andy Saputra, S.T.')
        ->and(SignatoryName::format('Budi Santoso, S.H.'))->toBe('Budi Santoso, S.H.'); // not a user
});

test('the signature is found with or without titles and degrees', function (string $name, string $file) {
    expect(basename((string) SignatureImage::pathFor($name)))->toBe($file);
})->with([
    ['Aldi PP, S.Ikom', 'aldi.png'],
    ['Aldi PP', 'aldi.png'],
    ['Andy Saputra, S.T.', 'andy.png'],
    ['Andy Saputra', 'andy.png'],
    ['Rizky Ajie Kurniawan, S.Kom.', 'rizky.png'],
    ['Rizky Ajie Kurniawan', 'rizky.png'],
    ['Rizky Aji Kurniawan', 'rizky.png'],
    ['Dr. Rizky Aji Kurniawan, S.Kom.', 'rizky.png'],
]);

test('the employee form (admin) stores the full name exactly, including removing a degree', function () {
    $user = User::factory()->create(['name' => 'Aldi PP, S.Ikom']);
    $repo = app(\App\Interfaces\UserRepositoryInterface::class);

    $repo->update((string) $user->id, ['email' => 'aldi@example.com']); // name not sent
    expect($user->fresh()->full_name)->toBe('Aldi PP, S.Ikom');

    $repo->update((string) $user->id, ['name' => 'Aldi PP']); // degree removed on purpose
    expect($user->fresh()->full_name)->toBe('Aldi PP');

    $repo->update((string) $user->id, ['name' => 'Aldi Pratama Putra, S.Ikom']);
    expect($user->fresh()->full_name)->toBe('Aldi Pratama Putra, S.Ikom');
});
