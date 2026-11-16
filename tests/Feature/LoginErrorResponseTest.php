<?php

use App\Interfaces\AuthRepositoryInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a database error during login returns a JSON 500 without server details', function () {
    // PDO errors carry a SQLSTATE string code ("HY000"), which used to crash
    // the error response itself (raw HTML 500) and leak the DB host/SQL.
    $this->mock(AuthRepositoryInterface::class)
        ->shouldReceive('login')
        ->andThrow(new PDOException('SQLSTATE[HY000] [2002] Connection refused (Host: 127.0.0.1, Database: hris_api)'));

    $response = $this->postJson('/api/v1/login', ['email' => 'someone@example.com', 'password' => 'secret123']);

    $response->assertStatus(500)->assertJsonPath('success', false);
    expect($response->json('message'))->not->toContain('SQLSTATE')->not->toContain('127.0.0.1');
});

test('wrong credentials still return 401 with their message', function () {
    User::factory()->create(['email' => 'real@example.com', 'password' => bcrypt('right-password')]);

    $this->postJson('/api/v1/login', ['email' => 'real@example.com', 'password' => 'wrong-password'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Unauthorized');
});
