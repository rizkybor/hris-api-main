<?php

use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('responses never use a non-HTTP status code', function ($given, $expected) {
    expect(ResponseHelper::jsonResponse(false, 'x', null, $given)->status())->toBe($expected);
})->with([
    'SQLSTATE string' => ['42S22', 500],
    'zero' => [0, 500],
    'numeric string' => ['404', 404],
    'valid int' => [401, 401],
]);

test('names and sign-in still work before users.show_degree is migrated', function () {
    $user = User::factory()->create(['name' => 'Aldi PP, S.Ikom', 'email' => 'aldi@example.com', 'password' => 'secret-pass-123']);
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('show_degree'));
    PersonName::flush();

    expect(User::find($user->id)->name)->toBe('Aldi PP');

    $this->postJson('/api/v1/login', ['email' => 'aldi@example.com', 'password' => 'secret-pass-123'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Aldi PP');
});
