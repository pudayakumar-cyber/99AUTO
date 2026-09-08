<?php

namespace App\Support;

use InvalidArgumentException;

class MarketingIdentity
{
    public static function normalize(string $channel, string $identity): string
    {
        return match ($channel) {
            'email' => self::email($identity),
            'sms' => self::phone($identity),
            default => throw new InvalidArgumentException('Unsupported marketing channel.'),
        };
    }

    public static function email(string $email): string
    {
        $normalized = strtolower(trim($email));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required for email marketing consent.');
        }

        return $normalized;
    }

    public static function phone(string $phone): string
    {
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($phone, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if (! $hasPlus && strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Use a valid phone number including its country code.');
        }

        return '+'.$digits;
    }
}
