{{ $greeting }}

Thank you for choosing {{ $product?->name }}. Your {{ $license->planLabel() }} license certificate is attached to this email as a PDF.
@if($keys->count() > 1)
It holds one key for each platform: use the Mac key on a Mac and the iPad key on an iPad.
@endif
@if(filled($personalMessage))

{{ $personalMessage }}
@endif

@foreach($keys as $key)
{{ $keys->count() > 1 ? $key->platformLabel().' license key' : 'License key' }}: {{ $key->key }}
@endforeach
Plan: {{ $license->planLabel() }}
Valid until: {{ $expiresOn ?? 'Lifetime, never expires' }}
Devices: up to {{ $license->max_devices }} at a time{{ $keys->count() > 1 ? ' per key' : '' }}

To activate, open {{ $product?->name }}, open License from the plan chip next to the app name, then paste the key and activate. Please keep the key private.

Kind regards,
PT Jendela Cakra Digital
jcdigital.co.id
