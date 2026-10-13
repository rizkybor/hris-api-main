@php
    $rp = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    // Values go into markdown tables, where a stray "|" would split a cell.
    $cell = fn ($value) => filled($value) ? str_replace(['|', "\n"], ['\|', ' '], (string) $value) : '-';
    $date = fn ($value) => $value ? $value->copy()->locale('id')->translatedFormat('d F Y') : '-';
    $client = $subscription->client;
    $cycle = $subscription->billing_cycle === 'yearly' ? 'Tahunan' : 'Bulanan';
    $status = ['active' => 'Aktif', 'postponed' => 'Ditunda', 'cancelled' => 'Tidak Aktif'][$subscription->status] ?? $subscription->status;
@endphp
<x-mail::message>
# Pengingat Tagihan Subscription

Tagihan subscription **{{ $subscription->name }}** untuk **{{ $client?->name ?? '-' }}** akan jatuh tempo
**{{ $daysRemaining === 0 ? 'hari ini' : "dalam {$daysRemaining} hari" }}**, pada **{{ $dueDate }}**.

<x-mail::panel>
**Total tagihan periode {{ $billing['period_label'] }}: {{ $rp($billing['total']) }}**
</x-mail::panel>

## Informasi Subscription

<x-mail::table>
| | |
|:--|:--|
| Nama Subscription | {{ $cell($subscription->name) }} |
| Project | {{ $cell($subscription->project?->name) }} |
| Siklus Tagihan | {{ $cycle }} |
| Periode Tagihan | {{ $cell($billing['period_label']) }} |
| Tanggal Jatuh Tempo | **{{ $dueDate }}** |
| Tanggal Mulai | {{ $date($subscription->start_date) }} |
| Invoice Terakhir | {{ $date($subscription->last_invoiced_at) }} |
| Status | {{ $status }} |
</x-mail::table>

## Informasi Klien

<x-mail::table>
| | |
|:--|:--|
| Nama Klien | {{ $cell($client?->name) }} |
| PIC | {{ $cell($client?->pic_name) }} |
| Telepon PIC | {{ $cell($client?->pic_phone) }} |
| Email | {{ $cell($client?->email) }} |
| NPWP | {{ $cell($client?->npwp) }} |
| Alamat | {{ $cell($client?->address) }} |
</x-mail::table>

## Rincian Layanan

<x-mail::table>
| Layanan | Produk | PPN | Biaya |
|:--|:--|:--:|--:|
@foreach ($subscription->services as $service)
| {{ $cell($service->service_type) }} | {{ $cell($service->product_name) }} | {{ rtrim(rtrim(number_format((float) $service->ppn_percentage, 2, ',', '.'), '0'), ',') }}% | {{ $rp($service->amount) }} |
@if ((float) ($service->icann_fee ?? 0) > 0)
| {{ $cell($service->service_type) }} – ICANN Fee | {{ $cell($service->product_name) }} | – | {{ $rp($service->icann_fee) }} |
@endif
@endforeach
</x-mail::table>

## Ringkasan Tagihan

<x-mail::table>
| | |
|:--|--:|
| Subtotal | {{ $rp($billing['subtotal']) }} |
| PPN ({{ rtrim(rtrim(number_format($billing['ppn_percentage'], 2, ',', '.'), '0'), ',') }}%) | {{ $rp($billing['ppn_amount']) }} |
@if ($billing['icann_fee'] > 0)
| ICANN Fee | {{ $rp($billing['icann_fee']) }} |
@endif
@if ($billing['admin_fee'] > 0)
| Biaya Admin | {{ $rp($billing['admin_fee']) }} |
@endif
| **Total Tagihan** | **{{ $rp($billing['total']) }}** |
</x-mail::table>

@if ($pph23Estimate)
*Tagihan ini dikenakan potongan PPh 23 sebesar {{ number_format((float) $subscription->pph23_percent, 0) }}% oleh klien saat pembayaran (estimasi {{ $rp($pph23Estimate) }}). Total tagihan di atas tidak berubah — potongan dicatat saat pembayaran diterima.*
@endif

## Pembayaran

<x-mail::table>
| | |
|:--|:--|
| Bank | {{ $cell($subscription->bank_name) }} |
| No. Rekening | {{ $cell($bankAccount?->account_number) }} |
| Ketentuan | {{ $cell($subscription->terms) }} |
</x-mail::table>

@if ($subscription->notes)
**Catatan:** {{ $subscription->notes }}
@endif

**Langkah selanjutnya:** buat invoice untuk periode ini melalui menu **Subscriptions** di Backoffice JCD (tombol *Generate Invoice*), lalu kirimkan ke klien sebelum tanggal jatuh tempo.

<x-mail::button :url="$actionUrl" color="primary">
Buka Subscriptions di Backoffice
</x-mail::button>

Email ini dikirim otomatis oleh Backoffice JCD, satu kali untuk setiap periode tagihan.

Salam,<br>
**PT. Jendela Cakra Digital**<br>
Jl. Pd. Cabe Raya No.7, Pd. Cabe Udik, Kec. Pamulang, Kota Tangerang Selatan, Banten 15418<br>
info@jcdigital.co.id | www.jcdigital.co.id | +62 87 8279 2511
</x-mail::message>
