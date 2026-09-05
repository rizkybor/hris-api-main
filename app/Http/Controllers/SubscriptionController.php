<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;

class SubscriptionController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentNumberService $numberService) {}

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['subscription-menu|subscription-list']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['subscription-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['subscription-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['subscription-delete']), only: ['destroy']),
            new Middleware(PermissionMiddleware::using(['subscription-generate-invoice']), only: ['generateInvoice']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = Subscription::with(['client', 'project'])
                ->withCount('invoices')
                ->orderBy('next_due_date');

            if ($request->search) {
                $query->search($request->search);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->service_type) {
                $query->where('service_type', $request->service_type);
            }

            $rowPerPage = (int) ($request->row_per_page ?? 10);
            // ->through() maps each row to a Resource while keeping the
            // paginator instance (and its current_page/last_page/total meta)
            // intact -- Resource::collection() on a paginator instead would
            // drop that meta once it's nested inside ResponseHelper's plain
            // array response.
            $subscriptions = $query->paginate($rowPerPage)->through(fn ($subscription) => new SubscriptionResource($subscription));

            return ResponseHelper::jsonResponse(true, 'Subscriptions Retrieved Successfully', $subscriptions, 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function store(SubscriptionStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $validated['created_by'] = Auth::id();
            $validated['status'] = $validated['status'] ?? 'active';
            $subscription = Subscription::create($validated);
            $subscription->load(['client', 'project']);

            return ResponseHelper::jsonResponse(true, 'Subscription Created Successfully', new SubscriptionResource($subscription), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function update(SubscriptionUpdateRequest $request, string $id)
    {
        $validated = $request->validated();

        try {
            $subscription = Subscription::findOrFail($id);
            $subscription->update($validated);
            $subscription->load(['client', 'project']);

            return ResponseHelper::jsonResponse(true, 'Subscription Updated Successfully', new SubscriptionResource($subscription), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Subscription Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $subscription->delete();

            return ResponseHelper::jsonResponse(true, 'Subscription Deleted Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Subscription Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Manual, staff-triggered billing: creates one Invoice for the
     * subscription's current period, then advances next_due_date by one
     * billing cycle in the same transaction -- a repeat click bills the
     * *next* period rather than duplicating the one just invoiced.
     * lockForUpdate prevents a double-click (or two concurrent requests)
     * from generating two invoices for the same period.
     */
    public function generateInvoice(string $id)
    {
        try {
            $invoice = DB::transaction(function () use ($id) {
                $subscription = Subscription::with('client')->lockForUpdate()->findOrFail($id);

                if ($subscription->status !== 'active') {
                    abort(422, 'Only active subscriptions can generate an invoice.');
                }

                $client = $subscription->client;
                $periodLabel = $subscription->billing_cycle === 'yearly'
                    ? $subscription->next_due_date->format('Y')
                    : $subscription->next_due_date->format('F Y');

                $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $client->name), 0, 4)) ?: 'CLI';
                $date = Carbon::now();

                $bankAccount = $subscription->bank_name
                    ? BankAccount::where('bank_name', $subscription->bank_name)->first()
                    : null;

                $subtotal = (float) (string) $subscription->amount;
                $ppnPercentage = (float) (string) $subscription->ppn_percentage;
                $ppnAmount = round($subtotal * ($ppnPercentage / 100));
                $adminFee = (float) (string) $subscription->admin_fee;

                $invoice = Invoice::create([
                    'invoice_number' => $this->numberService->generateInvoiceNumber($clientCode, $date),
                    'project_id' => $subscription->project_id,
                    'subscription_id' => $subscription->id,
                    'client_code' => $clientCode,
                    'client_name' => $client->name,
                    'client_pic' => $client->pic_name,
                    'client_email' => $client->email,
                    'client_phone' => $client->pic_phone,
                    'client_npwp' => $client->npwp,
                    'date' => $date,
                    'items' => [[
                        'description' => "{$subscription->name} - {$periodLabel}",
                        'quantity' => '1',
                        'rate' => null,
                        'total' => $subtotal,
                    ]],
                    'subtotal' => $subtotal,
                    'ppn_percentage' => $ppnPercentage,
                    'ppn_amount' => $ppnAmount,
                    'admin_fee' => $adminFee,
                    'bank_name' => $subscription->bank_name,
                    'bank_account' => $bankAccount?->account_number,
                    'terms' => $subscription->terms,
                    'pph23_type' => $subscription->pph23_type,
                    'pph23_percent' => $subscription->pph23_percent,
                    'total' => $subtotal + $ppnAmount + $adminFee,
                    'created_by' => Auth::id(),
                ]);

                $subscription->next_due_date = $subscription->billing_cycle === 'yearly'
                    ? $subscription->next_due_date->copy()->addYear()
                    : $subscription->next_due_date->copy()->addMonth();
                $subscription->last_invoiced_at = $date;
                $subscription->save();

                return $invoice;
            });

            return ResponseHelper::jsonResponse(true, 'Invoice Generated Successfully', $invoice, 201);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Subscription Not Found', null, 404);
        } catch (\Throwable $e) {
            $status = $e->getCode() === 422 ? 422 : 500;
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $status);
        }
    }
}
