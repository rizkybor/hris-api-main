<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Manager token signing
    |--------------------------------------------------------------------------
    |
    | Ed25519 keypair (base64, as produced by libsodium) used to sign the
    | license tokens the apps (OCCA, ...) verify offline. One
    | pair signs the tokens of every product. The public key is compiled into
    | each app, so rotating it requires app releases. The env names keep the
    | OCCA_ prefix they had when OCCA was the only product.
    | Generate a fresh pair with `php artisan license:keys`.
    |
    */

    'license_secret_key' => env('OCCA_LICENSE_SECRET_KEY'),
    'license_public_key' => env('OCCA_LICENSE_PUBLIC_KEY'),

    // The app should call /refresh after this many days...
    'license_refresh_after_days' => 7,

    // ...and keeps working offline for at most this many days (capped by
    // the license's own expiry).
    'license_offline_days' => 30,

    // Holographic artwork of the license certificate PDF: a (soft),
    // b (medium) or c (vivid but refined). The PNGs for all three are in
    // resources/license-products/ (php artisan license:certificate-assets).
    'certificate_hologram' => env('LICENSE_CERTIFICATE_HOLOGRAM', 'c'),

];
