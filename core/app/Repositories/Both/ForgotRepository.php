<?php

namespace App\Repositories\Both;

use App\Helpers\EmailHelper;
use Illuminate\Support\Facades\Hash;

class ForgotRepository
{
    /**
     * Forgot password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function forgot($data,$request,$auth)
    {
        $input =  $request->all();
        $token = md5(time().$data->name.$data->email);
        $input['email_token'] = $token;
        $data->update($input);
        $subject = "Reset Password Request";

$resetUrl = route($auth.'.change.token', $token);

$msg = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Reset Password Request</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:20px;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);">

<!-- Header -->
<tr>
<td style="background:#d71920; padding:20px; text-align:center;">
<img src="https://99autoparts.ca/core/public/storage/images/OM_17668398019e1RBgt0.png">
<p style="color:#ffffff; margin:5px 0 0; font-size:14px;">
Huge Selection of Quality Auto Parts
</p>
</td>
</tr>

<!-- Body -->
<tr>
<td style="padding:30px; color:#333333;">

<h2 style="margin-top:0; color:#111111;">
Password Reset Request
</h2>

<p style="font-size:15px; line-height:1.6;">
We received a request to reset your password for your <strong>99AutoParts</strong> account.
</p>

<div style="text-align:center; margin:30px 0;">
<a href="'.$resetUrl.'" style="background:#d71920; color:#ffffff; text-decoration:none; padding:14px 28px; font-size:14px; border-radius:4px; display:inline-block;">
Reset Password
</a>
</div>

<p style="font-size:14px; line-height:1.6;">
If the button above does not work, copy and paste the link below into your browser:
</p>

<p style="word-break:break-all; font-size:12px; color:#555555;">
'.$resetUrl.'
</p>

<p style="font-size:13px; color:#666666; line-height:1.6;">
If you did not request this password reset, please ignore this email.
</p>

<p style="margin-bottom:0;">
Regards,<br>
<strong>99 Auto Parts Team</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="background:#111111; padding:15px; text-align:center;">
<p style="color:#aaaaaa; font-size:12px; margin:0;">
© 99 Auto Parts. All rights reserved.
</p>
</td>
</tr>

</table>
</td>
</tr>
</table>
</body>
</html>
';


        $emailData = [
            'to' => $request->email,
            'subject' => $subject,
            'body' => $msg,
        ];
        $email = new EmailHelper();
        $email->sendCustomMail($emailData);
    }

    /**
     * Update password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function updatePassword($data,$request,$type)
    {
       
        if ($request->current_password){
            if (Hash::check($request->current_password, $data->password)){
                if ($request->new_password == $request->renew_password){
                    $input['password'] = Hash::make($request->new_password);
                }else{
                    return [
                        'status'  => false,
                        'message' => __('Confirm password does not match.')
                    ];
                }
            }else{
                return [
                    'status'  => false,
                    'message' => __('Current password Does not match.')
                ];
            }
        }
        
       
            if ($request->new_password == $request->renew_password){
                $input['password'] = Hash::make($request->new_password);
            }else{
                return [
                    'status'  => false,
                    'message' => __('Confirm password does not match.')
                ];
            }
        

        $input['email_token'] = null;
        $data->update($input);

        return [
            'status'       => true,
            'redurect_url' => route($type.'.login'),
            'message'      => __('Successfully changed your password')
        ];

    }

}
