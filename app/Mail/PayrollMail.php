<?php

namespace App\Mail;

use App\Models\Socio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Socio $socio,
        public string $period,
        public string $attachmentPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Busta paga {$this->period}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payroll');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->attachmentPath)
                ->as('busta-paga-'.str_replace(' ', '-', $this->period).'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
