<?php

namespace App\Repositories\Front;

use App\{
    Models\User,
    Models\Setting,
    Helpers\EmailHelper,
    Models\Notification
};
use App\Helpers\ImageHelper;
use App\Jobs\EmailSendJob;
use App\Models\Subscriber;
use App\Services\MarketingConsentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserRepository
{
    public function __construct(private MarketingConsentService $marketingConsentService)
    {
    }

    public function register($request)
    {


        $input = $request->all();

        $user = new User;
        $input['password'] = bcrypt($request['password']);
        $input['email'] = $input['email'];
        $input['first_name'] = $input['first_name'];
        $input['last_name'] = $input['last_name'];
        $input['phone'] = $input['phone'];
        $verify = rand(pow(10, 6 - 1), pow(10, 6) - 1);
        $input['email_token'] = $verify;
        $user->fill($input)->save();

        if ($request->boolean('email_marketing_consent')) {
            Subscriber::firstOrCreate(['email' => strtolower(trim($user->email))]);
            $this->marketingConsentService->setConsent(
                'email',
                $user->email,
                true,
                'account_registration',
                $user,
                MarketingConsentService::EMAIL_TEXT,
                $request->ip(),
                $request->userAgent()
            );
        }

        if ($request->boolean('sms_marketing_consent')) {
            $this->marketingConsentService->setConsent(
                'sms',
                $user->phone,
                true,
                'account_registration',
                $user,
                MarketingConsentService::SMS_TEXT,
                $request->ip(),
                $request->userAgent()
            );
        }


        Notification::create(['user_id' => $user->id]);
        $emailData = [
            'to' => $user->email,
            'subject' => "Email Verification",
            'body' => "Your verification code is " . $verify,
        ];
        $setting = Setting::first();

        if ($setting->is_mail_verify == 1) {
            if ($setting->is_queue_enabled == 1) {
                dispatch(new EmailSendJob($emailData));
            } else {
                $email = new EmailHelper();
                $email->sendCustomMail($emailData, "custom");
            }
        }
    }





    public function profileUpdate($request)
    {
        $input = $request->all();
        if ($request['user_id']) {
            $user = User::findOrFail($request['user_id']);
        } else {
            $user = Auth::user();
        }


        if ($request->password) {
            $input['password'] = bcrypt($input['password']);
            $user->password = $input['password'];
            $user->update();
        } else {
            unset($input['password']);
        }


        if ($file = $request->file('photo')) {
            $input['photo'] = ImageHelper::handleUpdatedUploadedImage($file, '/assets/images', $user, '/assets/images/', 'photo');
        }

        $oldEmail = $user->email;
        $oldPhone = $user->phone;
        $emailWasSubscribed = Subscriber::where('email', $oldEmail)->exists()
            || $this->marketingConsentService->isSubscribed('email', $oldEmail);
        $smsWasSubscribed = $this->marketingConsentService->isSubscribed('sms', $oldPhone);
        $emailShouldBeSubscribed = $request->boolean('email_marketing_consent');
        $smsShouldBeSubscribed = $request->boolean('sms_marketing_consent');

        $user->fill($input)->save();

        if ($oldEmail !== $user->email && $emailWasSubscribed) {
            Subscriber::where('email', $oldEmail)->delete();
            $this->marketingConsentService->setConsent(
                'email', $oldEmail, false, 'profile_identity_change', $user,
                MarketingConsentService::EMAIL_TEXT, $request->ip(), $request->userAgent()
            );
            $emailWasSubscribed = false;
        }

        if ($emailShouldBeSubscribed) {
            Subscriber::firstOrCreate(['email' => strtolower(trim($user->email))]);
            $this->marketingConsentService->setConsent(
                'email', $user->email, true, 'account_preferences', $user,
                MarketingConsentService::EMAIL_TEXT, $request->ip(), $request->userAgent()
            );
        } else {
            Subscriber::where('email', $user->email)->delete();
            if ($emailWasSubscribed) {
                $this->marketingConsentService->setConsent(
                    'email', $user->email, false, 'account_preferences', $user,
                    MarketingConsentService::EMAIL_TEXT, $request->ip(), $request->userAgent()
                );
            }
        }

        if ($oldPhone !== $user->phone && $smsWasSubscribed) {
            $this->marketingConsentService->setConsent(
                'sms', $oldPhone, false, 'profile_identity_change', $user,
                MarketingConsentService::SMS_TEXT, $request->ip(), $request->userAgent()
            );
            $smsWasSubscribed = false;
        }

        if ($smsShouldBeSubscribed) {
            $this->marketingConsentService->setConsent(
                'sms', $user->phone, true, 'account_preferences', $user,
                MarketingConsentService::SMS_TEXT, $request->ip(), $request->userAgent()
            );
        } elseif ($smsWasSubscribed) {
            $this->marketingConsentService->setConsent(
                'sms', $user->phone, false, 'account_preferences', $user,
                MarketingConsentService::SMS_TEXT, $request->ip(), $request->userAgent()
            );
        }
    }
}
