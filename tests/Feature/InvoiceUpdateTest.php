<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['manager', 'superadmin'] as $name) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
    }
    foreach (['invoice-edit', 'invoice-create'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::findByName('manager', 'sanctum')->givePermissionTo(['invoice-edit', 'invoice-create']);
    $this->user = User::factory()->create();
    $this->user->assignRole('manager');
    Sanctum::actingAs($this->user);

    $this->invoice = Invoice::create([
        'invoice_number' => 'INV/MANUAL/001',
        'client_code' => null,
        'client_name' => 'PT Zaco',
        'date' => '2026-11-12',
        'items' => [['description' => 'Hosting', 'quantity' => '1', 'rate' => '1500000', 'total' => 1500000]],
        'subtotal' => 1500000,
        'ppn_percentage' => 11,
        'ppn_amount' => 165000,
        'admin_fee' => 5000,
        'icann_fee' => 0,
        'total' => 1670000,
        'created_by' => $this->user->id,
    ]);
    $this->payload = [
        'client_name' => 'PT Zaco',
        'date' => '2026-11-12',
        'items' => [['description' => 'Hosting', 'quantity' => '1', 'rate' => '1500000', 'total' => 1500000]],
    ];
});

test('a manually numbered invoice (no client code) can be updated without one', function () {
    $this->putJson("/api/v1/invoices/{$this->invoice->id}", $this->payload + ['ppn_percentage' => 11, 'admin_fee' => 5000, 'icann_fee' => 0])
        ->assertOk();

    expect($this->invoice->fresh()->invoice_number)->toBe('INV/MANUAL/001');
});

test('emptied fees are saved as 0 and the total uses them', function () {
    $this->putJson("/api/v1/invoices/{$this->invoice->id}", $this->payload + ['ppn_percentage' => null, 'admin_fee' => null, 'icann_fee' => null])
        ->assertOk();

    $fresh = $this->invoice->fresh();
    expect((float) $fresh->admin_fee)->toBe(0.0)
        ->and((float) $fresh->ppn_percentage)->toBe(0.0)
        ->and((float) $fresh->total)->toBe(1500000.0);
});

test('fees left out keep their saved value', function () {
    $this->putJson("/api/v1/invoices/{$this->invoice->id}", $this->payload)->assertOk();

    expect((float) $this->invoice->fresh()->total)->toBe(1670000.0);
});

test('a new invoice needs a bank name and account number', function () {
    $base = $this->payload + ['numbering_mode' => 'manual', 'invoice_number' => 'INV/MANUAL/002'];

    $this->postJson('/api/v1/invoices', $base)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_name', 'bank_account']);

    $this->postJson('/api/v1/invoices', $base + ['bank_name' => 'BCA', 'bank_account' => '1234567890'])
        ->assertCreated();
});

test('an update cannot empty the bank name or account number', function () {
    $this->putJson("/api/v1/invoices/{$this->invoice->id}", $this->payload + ['bank_name' => '', 'bank_account' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_name', 'bank_account']);
});
