<?php

use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['invoice-list', 'payment-receipt-list', 'purchase-order-list'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'sanctum'])->givePermissionTo(['invoice-list', 'payment-receipt-list', 'purchase-order-list']);
    $user = User::factory()->create();
    $user->assignRole('finance');
    Sanctum::actingAs($user);

    $invoice = fn ($n, $date, $total, $status) => Invoice::create(['invoice_number' => $n, 'client_code' => 'A', 'client_name' => 'PT A', 'date' => $date, 'items' => [], 'total' => $total, 'status' => $status]);
    $invoice('I1', '2026-11-01', 1000000, 'paid');
    $invoice('I2', '2026-11-30', 2500000, 'unpaid');
    $invoice('I3', '2026-11-15', 9000000, 'cancelled');
    $invoice('I4', '2026-10-31', 4000000, 'paid');

    $receipt = fn ($n, $date, $amount, $pph, $status) => PaymentReceipt::create(['receipt_number' => $n, 'client_code' => 'A', 'date' => $date, 'received_from' => 'PT A', 'amount' => $amount, 'pph23_amount' => $pph, 'for_payment_of' => 'Jasa', 'status' => $status]);
    $receipt('R1', '2026-11-10', 980000, 20000, 'active');
    $receipt('R2', '2026-11-12', 500000, 0, 'cancelled');

    $po = fn ($n, $date, $type, $total, $status) => PurchaseOrder::create(['po_number' => $n, 'type' => $type, 'date' => $date, 'title' => 'T', 'client_name' => 'PT A', 'items' => [], 'total' => $total, 'status' => $status]);
    $po('P1', '2026-11-05', 'E', 3000000, 'active');
    $po('P2', '2026-11-06', 'I', 700000, 'active');
    $po('P3', '2026-11-07', 'E', 100, 'cancelled');
});

test('invoice totals for a period (cancelled left out, period edges included)', function () {
    $this->getJson('/api/v1/invoices/summary?from=2026-11-01&to=2026-11-30')
        ->assertOk()
        ->assertJsonPath('data.billed', 3500000)
        ->assertJsonPath('data.billed_count', 2)
        ->assertJsonPath('data.paid', 1000000)
        ->assertJsonPath('data.unpaid', 2500000)
        ->assertJsonPath('data.cancelled_count', 1);

    $this->getJson('/api/v1/invoices/summary')->assertJsonPath('data.billed', 7500000);
    $this->getJson('/api/v1/invoices/summary?from=2026-12-01&to=2026-11-01')->assertUnprocessable();
});

test('receipt totals: received is net, gross adds PPh 23', function () {
    $this->getJson('/api/v1/payment-receipts/summary?from=2026-11-01&to=2026-11-30')
        ->assertOk()
        ->assertJsonPath('data.received', 980000)
        ->assertJsonPath('data.pph23', 20000)
        ->assertJsonPath('data.gross', 1000000)
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.cancelled_count', 1);
});

test('purchase order totals split external and internal', function () {
    $this->getJson('/api/v1/purchase-orders/summary?from=2026-11-01&to=2026-11-30')
        ->assertOk()
        ->assertJsonPath('data.total', 3700000)
        ->assertJsonPath('data.external', 3000000)
        ->assertJsonPath('data.internal', 700000)
        ->assertJsonPath('data.count', 2);
});

test('each summary needs its list permission', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/invoices/summary')->assertForbidden();
    $this->getJson('/api/v1/payment-receipts/summary')->assertForbidden();
    $this->getJson('/api/v1/purchase-orders/summary')->assertForbidden();
});
