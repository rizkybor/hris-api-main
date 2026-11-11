<?php

namespace App\Mail;

use App\Models\License;
use App\Models\LicenseKey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Licensee-facing email with the License Certificate PDF attached (one PDF
 * for a Mac + iPad bundle, carrying both keys). Sent as PT Jendela Cakra
 * Digital, replies going to the company inbox.
 */
class LicenseCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, License>  $keys  the licenses the certificate covers
     */
    /** Per-device keys listed in the email body (per platform); more are only in the PDF. */
    public const MAX_LISTED_KEYS = 10;

    public function __construct(
        public License $license,
        public Collection $keys,
        public string $pdf,
        public string $filename,
        public ?string $recipientName,
        public ?string $personalMessage,
        // A certificate for this one key only.
        public ?LicenseKey $singleKey = null,
    ) {}

    public function envelope(): Envelope
    {
        $product = $this->license->product?->name ?? 'Software';

        return new Envelope(
            from: new Address(config('mail.from.address'), 'PT Jendela Cakra Digital'),
            replyTo: [new Address(config('services.proposal_share.reply_to'), 'PT Jendela Cakra Digital')],
            subject: "Your {$product} {$this->license->planLabel()} License | PT Jendela Cakra Digital",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.licenses.certificate',
            text: 'emails.licenses.certificate-text',
            with: [
                'license' => $this->license,
                'keys' => $this->keys,
                'product' => $this->license->product,
                'greeting' => filled($this->recipientName) ? "Dear {$this->recipientName}," : 'Dear Sir/Madam,',
                'personalMessage' => $this->personalMessage,
                'expiresOn' => $this->license->expires_at?->copy()->timezone('Asia/Jakarta')->format('j F Y'),
                // "One key per device": the keys per license (listed in the
                // email up to MAX_LISTED_KEYS each, always in the PDF).
                'perDevice' => $this->license->isPerDevice() && ! $this->singleKey,
                'singleKey' => $this->singleKey,
                'keysTotal' => $this->singleKey ? $this->license->keys()->count() : null,
                'deviceKeys' => $this->license->isPerDevice() && ! $this->singleKey
                    ? $this->keys->map(fn (License $member) => ['license' => $member, 'keys' => $member->keys()->get()])
                    : collect(),
                'maxListed' => self::MAX_LISTED_KEYS,
            ],
        );
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
