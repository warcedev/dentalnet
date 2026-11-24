<?php

namespace App\Mail;

use App\Services\Reminders\Channels\ReminderMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly ReminderMessage $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminder->subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reminders.reminder', with: ['message' => $this->reminder]);
    }
}
