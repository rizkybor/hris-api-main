<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LicenseKeysCommand extends Command
{
    protected $signature = 'license:keys';

    protected $description = 'Print a fresh Ed25519 keypair (base64) for License Manager tokens (all products). Never writes any file.';

    public function handle(): int
    {
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = base64_encode(sodium_crypto_sign_secretkey($keypair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));

        $this->line('Add these lines to .env (keep the secret key private):');
        $this->newLine();
        $this->line('OCCA_LICENSE_SECRET_KEY='.$secretKey);
        $this->line('OCCA_LICENSE_PUBLIC_KEY='.$publicKey);
        $this->newLine();
        $this->warn('The public key is built into every app (OCCA, ...). Changing the pair invalidates every token already issued, and the app must ship the new public key.');

        return self::SUCCESS;
    }
}
