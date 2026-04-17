<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'demo' => [
        'enabled' => false,
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'facebook' => [
        'pixel_id' => env('FACEBOOK_PIXEL_ID', '2388576101564001'),
        'conversion_api_token' => env('FACEBOOK_CONVERSION_API_TOKEN'),
    ],

    'eshipper' => [
        'enabled' => env('ESHIPPER_ENABLED', false),
        'mode' => env('ESHIPPER_MODE', 'live'),
        'live_url' => env('ESHIPPER_LIVE_URL', env('ESHIPPER_API_URL', 'https://ww2.eshipper.com')),
        'sandbox_url' => env('ESHIPPER_SANDBOX_URL', 'https://uu2.eshipper.com'),
        'username' => env('ESHIPPER_USERNAME'),
        'password' => env('ESHIPPER_PASSWORD'),
        'timeout' => env('ESHIPPER_TIMEOUT', 20),
        'connect_timeout' => env('ESHIPPER_CONNECT_TIMEOUT', 10),
        'allow_live_shipments' => env('ESHIPPER_ALLOW_LIVE_SHIPMENTS', false),
        'top_rates_limit' => env('ESHIPPER_TOP_RATES_LIMIT', 4),
        'free_shipping_threshold' => env('ESHIPPER_FREE_SHIPPING_THRESHOLD', 150),
        'fallback_flat_rate' => env('ESHIPPER_FALLBACK_FLAT_RATE', 25),
        'packaging_unit' => env('ESHIPPER_PACKAGING_UNIT', 'Imperial'),
        'currency' => env('ESHIPPER_CURRENCY', 'CAD'),
        'origin_contact' => env('ESHIPPER_ORIGIN_CONTACT', '99Auto'),
        'origin_company' => env('ESHIPPER_ORIGIN_COMPANY', '99Auto'),
        'origin_email' => env('ESHIPPER_ORIGIN_EMAIL', '99automotiveparts@gmail.com'),
        'origin_phone' => env('ESHIPPER_ORIGIN_PHONE', '289-271-5870'),
        'origin_address1' => env('ESHIPPER_ORIGIN_ADDRESS1'),
        'origin_address2' => env('ESHIPPER_ORIGIN_ADDRESS2'),
        'origin_city' => env('ESHIPPER_ORIGIN_CITY', 'Markham'),
        'origin_province' => env('ESHIPPER_ORIGIN_PROVINCE', 'Ontario'),
        'origin_postal' => env('ESHIPPER_ORIGIN_POSTAL', 'L3S 1R9'),
        'origin_country' => env('ESHIPPER_ORIGIN_COUNTRY', 'CA'),
    ],

];
