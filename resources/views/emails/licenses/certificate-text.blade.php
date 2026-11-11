{{ $greeting }}

Thank you for choosing {{ $product?->name }}. Your {{ $license->planLabel() }} license certificate is attached to this email as a PDF.
@if($keys->count() > 1)
It holds one key for each platform: use the Mac key on a Mac and the iPad key on an iPad.
@endif
@if(filled($personalMessage))

{{ $personalMessage }}
@endif

@if($perDevice)
@foreach($deviceKeys as $group)
{{ $keys->count() > 1 ? $group['license']->platformLabel().' keys' : 'License keys' }} ({{ $group['keys']->count() }}, one per device):
@if($group['keys']->count() <= $maxListed)
@foreach($group['keys'] as $deviceKey)
- {{ $deviceKey->key }}
@endforeach
@else
Listed on the Device Keys page of the attached certificate.
@endif
@endforeach
@else
@foreach($keys as $key)
{{ $keys->count() > 1 ? $key->platformLabel().' license key' : 'License key' }}: {{ $key->key }}
@endforeach
@endif
Plan: {{ $license->planLabel() }}
Valid until: {{ $expiresOn ?? 'Lifetime, never expires' }}
Devices: {{ $perDevice ? $license->max_devices.', one key each (a key cannot be reused once its device is released)' : 'up to '.$license->max_devices.' at a time'.($keys->count() > 1 ? ' per key' : '') }}

To activate, open {{ $product?->name }}, open License from the plan chip next to the app name, then paste the key and activate. Please keep the key private.

Kind regards,
PT Jendela Cakra Digital
jcdigital.co.id
