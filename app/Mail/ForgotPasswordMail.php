<?php

namespace App\Mail;

use App\Models\DetailEvent;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Crypt;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;
    public $resetLink;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($forgotToken)
    {
        $this->resetLink = env('FORGOT_PASSWORD_URL') . '?t=' . $forgotToken;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Atur Ulang Kata Sandi',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'mail.forgot-password',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
