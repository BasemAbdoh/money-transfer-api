<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserNumberMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userNumber;
    public $username;

    public function __construct(string $userNumber, string $username)
    {
        $this->userNumber = $userNumber;
        $this->username = $username;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رقمك المميز في نظام التحويلات',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user_number',
            with: [
                'userNumber' => $this->userNumber,
                'username'   => $this->username,
            ]
        );
    }
}
