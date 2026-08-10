<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز إعادة تعيين كلمة المرور - التطبيق المالي',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div dir='rtl' style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #601B8C;'>إعادة تعيين كلمة المرور</h2>
                    <p>عزيزي المستخدم، لقد طلبت إعادة تعيين كلمة المرور الخاصة بك.</p>
                    <p>رمز التحقق الخاص بك (OTP) هو:</p>
                    <div style='background: #f4f0f8; border-radius: 8px; padding: 15px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #601B8C;'>
                        {$this->otp}
                    </div>
                    <p style='margin-top: 20px; font-size: 12px; color: #777;'>هذا الرمز صالحة لمدة 15 دقيقة فقط.</p>
                </div>
            ",
        );
    }
}
