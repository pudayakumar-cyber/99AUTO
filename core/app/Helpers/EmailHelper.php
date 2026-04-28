<?php

/**
 * Created by UniverseCode.
 */

namespace App\Helpers;

use App\{
    Models\EmailTemplate,
    Models\Order,
    Models\Setting
};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer\{
    PHPMailer,
    Exception
};

class EmailHelper
{

    public $mail;
    public $setting;

    public function __construct()
    {
        $this->setting = Setting::first();

        $this->mail = new PHPMailer(true);

        if ($this->setting->smtp_check == 1) {

            $this->mail->isSMTP();
            $this->mail->Host       = $this->setting->email_host;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $this->setting->email_user;
            $this->mail->Password   = $this->setting->email_pass;
            if ($this->setting->email_encryption == 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $this->mail->Port           = $this->setting->email_port;
            $this->mail->CharSet        = 'UTF-8';
        }
    }

    public function sendTemplateMail(array $emailData)
    {
        $template = EmailTemplate::whereType($emailData['type'])->first();
        try {
            $email_body = $this->replaceTemplateTokens($template->body, $emailData);

            $this->mail->setFrom($this->setting->email_from, $this->setting->email_from_name);
            $this->mail->addAddress($emailData['to']);
            $this->mail->isHTML(true);
            $this->mail->Subject = $template->subject;
            $this->mail->Body = $email_body;
            $this->mail->send();
            Log::info('Template email sent.', $this->buildEmailLogContext($emailData, [
                'template_type' => $emailData['type'] ?? null,
                'recipient' => $emailData['to'] ?? null,
            ]));
            if ($this->shouldSendAdminOrderMail($emailData)) {
                $this->adminMail($emailData);
            }
        } catch (Exception $e) {
            Log::error('Template email send failed.', $this->buildEmailLogContext($emailData, [
                'template_type' => $emailData['type'] ?? null,
                'recipient' => $emailData['to'] ?? null,
                'error' => $e->getMessage(),
            ]));
        }

        return true;
    }

    public function sendCustomMail(array $emailData)
    {

        try {

            $this->mail->setFrom($this->setting->email_from, $this->setting->email_from_name);
            $this->mail->addAddress($emailData['to']);
            $this->mail->isHTML(true);
            $this->mail->Subject = $emailData['subject'];
            $this->mail->Body = $emailData['body'];

            $this->mail->send();
        } catch (Exception $e) {
            dd($e->getMessage());
        }

        return true;
    }


    public static function getEmail()
    {
        $user = Auth::user();
        if (isset($user)) {
            $email = $user->email;
        } else {
            $email = Session::get('billing_address')['bill_email'];
        }
        return $email;
    }


    public function adminMail(array $emailData)
    {

        try {

            $template = EmailTemplate::whereType('New Order Admin')->first();
            $email_body = $this->replaceTemplateTokens($template->body, $emailData);
            $this->mail->setFrom($this->setting->email_from, $this->setting->email_from_name);
            $this->mail->clearAddresses();
            $this->mail->addAddress($this->setting->contact_email);
            $this->mail->isHTML(true);
            $this->mail->Subject = $template->subject;
            $this->mail->Body = $email_body;
            $this->mail->send();
            Log::info('Admin order email sent.', $this->buildEmailLogContext($emailData, [
                'template_type' => 'New Order Admin',
                'recipient' => $this->setting->contact_email,
            ]));
        } catch (\Throwable $th) {
            Log::error('Admin order email send failed.', $this->buildEmailLogContext($emailData, [
                'template_type' => 'New Order Admin',
                'recipient' => $this->setting->contact_email,
                'error' => $th->getMessage(),
            ]));
        }
    }

    protected function replaceTemplateTokens(string $body, array $emailData): string
    {
        $replacements = [
            '{user_name}' => $emailData['user_name'] ?? '',
            '{order_cost}' => $emailData['order_cost'] ?? '',
            '{order_total}' => $emailData['order_cost'] ?? '',
            '{transaction_number}' => $emailData['transaction_number'] ?? '',
            '{order_number}' => $emailData['transaction_number'] ?? '',
            '{site_title}' => $emailData['site_title'] ?? $this->setting->title,
        ];

        return strtr($body, $replacements);
    }

    protected function shouldSendAdminOrderMail(array $emailData): bool
    {
        if ((int) $this->setting->order_mail !== 1) {
            return false;
        }

        $templateType = trim((string) ($emailData['type'] ?? ''));
        if ($templateType !== 'Order') {
            Log::info('Skipped admin order email because template type is not an order event.', $this->buildEmailLogContext($emailData, [
                'template_type' => $templateType,
                'reason' => 'non_order_template',
            ]));
            return false;
        }

        $transactionNumber = trim((string) ($emailData['transaction_number'] ?? ''));
        $orderCost = $emailData['order_cost'] ?? null;
        $normalizedOrderCost = is_string($orderCost) ? trim($orderCost) : $orderCost;

        if ($transactionNumber === '' || $normalizedOrderCost === null || $normalizedOrderCost === '') {
            Log::warning('Skipped admin order email because order data is incomplete.', $this->buildEmailLogContext($emailData, [
                'template_type' => $emailData['type'] ?? null,
                'reason' => 'missing_transaction_or_total',
            ]));
            return false;
        }

        $orderExists = Order::where('transaction_number', $transactionNumber)->exists();
        if (! $orderExists) {
            Log::warning('Skipped admin order email because no matching order exists.', $this->buildEmailLogContext($emailData, [
                'template_type' => $emailData['type'] ?? null,
                'reason' => 'missing_order_record',
            ]));
            return false;
        }

        return true;
    }

    protected function buildEmailLogContext(array $emailData, array $extra = []): array
    {
        $transactionNumber = $emailData['transaction_number'] ?? null;
        $orderId = null;

        if ($transactionNumber) {
            $orderId = Order::where('transaction_number', $transactionNumber)->value('id');
        }

        return array_merge([
            'order_id' => $orderId,
            'transaction_number' => $transactionNumber,
            'user_name' => $emailData['user_name'] ?? null,
            'order_cost' => $emailData['order_cost'] ?? null,
        ], $extra);
    }
}
