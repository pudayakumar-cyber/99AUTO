<?php

namespace App\Http\Controllers\Auth\User;

use App\{
  Http\Controllers\Controller,
  Http\Requests\AuthRequest,
};
use App\Helpers\EmailHelper;
use App\Jobs\EmailSendJob;
use App\Models\Setting;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
  public function __construct()
  {

    $this->middleware('guest', ['except' => ['logout', 'userLogout', 'verifySubmit']]);

    $setting = Setting::first();
    if ($setting->recaptcha == 1) {
      Config::set('captcha.sitekey', $setting->google_recaptcha_site_key);
      Config::set('captcha.secret', $setting->google_recaptcha_secret_key);
    }
  }

  public function showForm()
  {

    return view('user.auth.login');
  }

  public function login(AuthRequest $request)
  {

    // Attempt to log the user in
    if (Auth::attempt(['email' => $request->login_email, 'password' => $request->login_password])) {
      // if successful, then redirect to their intended location
      $setting = Setting::first();

      if (!Auth::user()->email_verify && $setting->is_mail_verify == 1) {
        Session::flash('error', __('Email not verify ! Please check your email for verification code.'));

        $user = Auth::user();
        $verify = rand(pow(10, 6 - 1), pow(10, 6) - 1);
        $emailData = [
    'to' => $user->email,
    'subject' => 'Email Verification Code',
    'body' => '
              <!DOCTYPE html>
              <html>
              <head>
              <meta charset="UTF-8">
              </head>
              <body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">

              <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
              <tr>
              <td align="center">

              <table width="600" style="background:#ffffff; border-radius:6px; overflow:hidden;">

              <tr>
              <td style="background:#d71920; padding:20px; text-align:center;">
              <img src="https://99autoparts.ca/core/public/storage/images/OM_17668398019e1RBgt0.png" style="max-width:160px;">
              <p style="color:#fff; margin:5px 0 0;">Email Verification</p>
              </td>
              </tr>

              <tr>
              <td style="padding:30px; color:#333;">
              <h2>Hello '.$user->displayName().',</h2>

              <p>Please use the verification code below to confirm your email address:</p>

              <div style="background:#f9f9f9; border-left:4px solid #d71920; padding:15px; font-size:22px; letter-spacing:3px; text-align:center; font-weight:bold;">
              '.$verify.'
              </div>

              <p style="margin-top:20px;">
              If you didn’t try to log in, please ignore this email.
              </p>

              <p>
              Regards,<br>
              <strong>'.Setting::first()->title.' Team</strong>
              </p>
              </td>
              </tr>

              <tr>
              <td style="background:#111; color:#aaa; padding:12px; text-align:center; font-size:12px;">
              © '.Setting::first()->title.'
              </td>
              </tr>

              </table>

              </td>
              </tr>
              </table>

              </body>
              </html>
              '
              ];

        $user->update(['email_token' => $verify]);


        if ($setting->is_mail_verify == 1) {
          if ($setting->is_queue_enabled == 1) {
            dispatch(new EmailSendJob($emailData));
          } else {
            $email = new EmailHelper();
            $email->sendCustomMail($emailData, "custom");
          }
          Auth::logout();
          return redirect()->route("user.verify");
        }
      }


      if ($request->has('modal')) {
        return redirect()->back();
      } else {
        return redirect()->intended(route('user.dashboard'));
      }
    }

    // if unsuccessful, then redirect back to the login with the form data
    Session::flash('error', __('Email Or Password Doesn\'t Match !'));
    return redirect()->back();
  }

  public function showVerifyForm()
  {

    return view('user.auth.verify');
  }

  public function verifySubmit(Request $request)
  {


    $user = User::where('email_token', $request->verify)->first();
    if (!$user) {
      Session::flash('error', __("Verify Code Doesn't Match !"));
      return redirect()->back();
    }
    if ($user->email_token == $request->verify) {
      $user->update(['email_token' => null, 'email_verify' => 1]);

      $setting = Setting::first();
      $emailData = [
        'to' => $user->email,
        'type' => "Registration",
        'user_name' => $user->displayName(),
        'order_cost' => '',
        'transaction_number' => '',
        'site_title' => Setting::first()->title,
      ];

      if ($setting->is_mail_verify == 1) {
        dispatch(new EmailSendJob($emailData,"template"));
      } else {
        $email = new EmailHelper();
        $email->sendTemplateMail($emailData, "template");
      }

      Auth::login($user);
      return redirect()->route('user.dashboard')->withSuccess(__('Email Verified Successfully.'));
    }


    // if unsuccessful, then redirect back to the login with the form data
    Session::flash('error', __('Email Or Password Doesn\'t Match !'));
    return redirect()->back();
  }

  public function logout()
  {
    Auth::logout();
    return redirect('/');
  }
}
