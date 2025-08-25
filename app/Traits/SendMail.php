<?php

namespace App\Traits;

use App\Mail\EmailNotificationMail;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Support\Facades\Mail;

trait SendMail
{

    public static function sendMail(User $user, $subject = "Verify Account Mail", $view = "email.verify",  ?string $otp = null)
    {
        Mail::to($user->email)
            ->send(new EmailNotificationMail($user, $otp ?? '0000', $subject, $view));
    }

     public function sendToEmail(string $email, $subject = "Verify Account Mail", $view = "email.verify",  ?string $otp = null,  ?User $user = null)
    {
        Mail::to($email)
            ->send(new EmailNotificationMail($user, $otp ?? '0000', $subject, $view));
    }


    public function sendToMultiple(array $emails, $subject = "Verify Account Mail", $view = "email.verify",  ?string $otp = null, ?User $user = null)
    {
        Mail::to($emails)->send(new EmailNotificationMail($user, $otp ?? '0000', $subject, $view));
    }
}
