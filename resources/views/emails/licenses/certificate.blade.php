<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product?->name }} License</title>
</head>
{{-- Same table layout and inline styles as the proposal share email. --}}
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:Helvetica, Arial, sans-serif; color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #dfe3ea; border-radius:8px;">
                <tr>
                    <td style="padding:22px 32px; border-bottom:1px solid #e6e9ef;">
                        <span style="font-size:13px; font-weight:bold; letter-spacing:1.5px; color:#0b1d51;">PT JENDELA CAKRA DIGITAL</span><br>
                        <span style="font-size:12px; color:#64748b;">Software House &amp; Digital Agency</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px 32px 8px 32px;">
                        <p style="margin:0 0 6px 0; font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#0c51d9;">SOFTWARE LICENSE</p>
                        <h1 style="margin:0 0 22px 0; font-size:22px; line-height:1.3; color:#0b1d51;">{{ $product?->name }} {{ $license->planLabel() }} License</h1>

                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.65;">{{ $greeting }}</p>
                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.65;">
                            Thank you for choosing {{ $product?->name }}. Your license certificate is attached to this email as a PDF.
                            @if($keys->count() > 1)
                                It holds one key for each platform: use the Mac key on a Mac and the iPad key on an iPad.
                            @endif
                        </p>

                        @if(filled($personalMessage))
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px 0;">
                                <tr>
                                    <td style="border-left:3px solid #0c51d9; background-color:#f3f6fb; padding:12px 16px; font-size:14px; line-height:1.65; color:#1f2937;">
                                        {!! nl2br(e($personalMessage)) !!}
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e9ef; border-bottom:1px solid #e6e9ef; margin:0 0 22px 0;">
                            @if($perDevice)
                                @foreach($deviceKeys as $group)
                                    <tr>
                                        <td style="padding:10px 0; font-size:13px; color:#64748b; width:38%; vertical-align:top; {{ $loop->first ? '' : 'border-top:1px solid #f0f2f6;' }}">
                                            {{ $keys->count() > 1 ? $group['license']->platformLabel().' keys' : 'License keys' }}<br>
                                            <span style="font-size:12px;">{{ $group['keys']->count() }} &middot; one per device</span>
                                        </td>
                                        <td style="padding:10px 0; font-size:13px; color:#0b1d51; font-weight:bold; font-family:Menlo, Consolas, monospace; letter-spacing:1px; line-height:1.7; {{ $loop->first ? '' : 'border-top:1px solid #f0f2f6;' }}">
                                            @if($group['keys']->count() <= $maxListed)
                                                @foreach($group['keys'] as $deviceKey){{ $deviceKey->key }}<br>@endforeach
                                            @else
                                                <span style="font-family:Helvetica, Arial, sans-serif; font-weight:normal; color:#1f2937;">Listed on the Device Keys page of the attached certificate.</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                @foreach($keys as $key)
                                    <tr>
                                        <td style="padding:10px 0; font-size:13px; color:#64748b; width:38%; {{ $loop->first ? '' : 'border-top:1px solid #f0f2f6;' }}">{{ $keys->count() > 1 ? $key->platformLabel().' license key' : 'License key' }}</td>
                                        <td style="padding:10px 0; font-size:14px; color:#0b1d51; font-weight:bold; font-family:Menlo, Consolas, monospace; letter-spacing:1px; {{ $loop->first ? '' : 'border-top:1px solid #f0f2f6;' }}">{{ $key->key }}</td>
                                    </tr>
                                @endforeach
                            @endif
                            <tr>
                                <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Plan</td>
                                <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $license->planLabel() }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Valid until</td>
                                <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $expiresOn ?? 'Lifetime, never expires' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px 0; font-size:13px; color:#64748b; border-top:1px solid #f0f2f6;">Devices</td>
                                <td style="padding:10px 0; font-size:13px; color:#1f2937; font-weight:bold; border-top:1px solid #f0f2f6;">{{ $perDevice ? $license->max_devices.', one key each (a key cannot be reused once its device is released)' : 'Up to '.$license->max_devices.' at a time'.($keys->count() > 1 ? ' per key' : '') }}</td>
                            </tr>
                        </table>

                        <p style="margin:0 0 14px 0; font-size:14px; line-height:1.65;">
                            To activate, open {{ $product?->name }}, open License from the plan chip next to the app name, then paste the key and activate.
                            Please keep the key private.
                        </p>
                        <p style="margin:0 0 26px 0; font-size:15px; line-height:1.65;">
                            Kind regards,<br>
                            <strong>PT Jendela Cakra Digital</strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px; border-top:1px solid #e6e9ef; font-size:12px; line-height:1.6; color:#64748b;">
                        PT Jendela Cakra Digital &middot; jcdigital.co.id<br>
                        Reply to this email if you have any questions about your license.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
