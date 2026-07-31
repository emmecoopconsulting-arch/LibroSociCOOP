<?php

namespace App\Mail;

use App\Models\MassCommunication;
use App\Models\Socio;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MassCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MassCommunication $communication,
        public Socio $socio,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->communication->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.mass-communication');
    }

    public function attachments(): array
    {
        if (blank($this->communication->attachment_path)) {
            return [];
        }

        return [
            Attachment::fromPath(Storage::disk('local')->path($this->communication->attachment_path))
                ->as($this->communication->attachment_name ?: basename($this->communication->attachment_path)),
        ];
    }
}
