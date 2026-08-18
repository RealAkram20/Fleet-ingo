<?php

namespace App\Mail;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from Settings to prove the SMTP details actually work.
 */
class TestMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Settings::get('app_name').' — test message',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
            with: ['appName' => Settings::get('app_name')],
        );
    }
}
