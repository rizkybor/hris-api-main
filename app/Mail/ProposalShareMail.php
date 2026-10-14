<?php

namespace App\Mail;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Client-facing email carrying a Proposal Penawaran's share link. Sent as
 * PT Jendela Cakra Digital (not the app name), with replies going to the
 * company inbox.
 */
class ProposalShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Proposal $proposal,
        public ?string $recipientName,
        public ?string $personalMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'PT Jendela Cakra Digital'),
            replyTo: [new Address(config('services.proposal_share.reply_to'), 'PT Jendela Cakra Digital')],
            subject: "Proposal Penawaran — {$this->proposal->title} | PT Jendela Cakra Digital",
        );
    }

    public function content(): Content
    {
        $proposal = $this->proposal;

        return new Content(
            view: 'emails.proposals.share',
            text: 'emails.proposals.share-text',
            with: [
                'proposal' => $proposal,
                'greeting' => filled($this->recipientName) ? "Yth. {$this->recipientName}," : 'Yth. Bapak/Ibu,',
                'personalMessage' => $this->personalMessage,
                'url' => $proposal->shareUrl(),
                'date' => $proposal->proposal_date->copy()->locale('id')->translatedFormat('j F Y'),
                'expiresAt' => $proposal->share_expires_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y'),
            ],
        );
    }
}
