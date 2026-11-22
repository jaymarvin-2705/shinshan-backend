<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetOtpMail extends Mailable
{
    public function __construct(
        public string $fullname,
        public string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Password Reset Code');
    }

    public function content(): Content
    {
        $name = e($this->fullname);

        return new Content(htmlString: "
            <p>Hello {$name},</p>
            <p>Your password reset code is:</p>
            <h2 style='letter-spacing:4px'>{$this->otp}</h2>
            <p>This code expires in 10 minutes. If you didn't request it, ignore this email.</p>
        ");
    }
}