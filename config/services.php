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

    'slack' => [
        'token' => env('SLACK_TOKEN')
    ],

    'ec-cube' => [
        'client_id' => '52bb9347cf6b20b7eced4d34dbeaba48',
        'client_secret' => 'a30a5418041268dd3876cdeb7c35a5717d1095bd7784571bf79686c5541b8fbb4f7b91028ac1e958b6f6cb5f0b9f65a22a22641694284172c47325fe633088ba',
        'redirect' => 'http://localhost:80/test/ec-cube/redirect'
    ]
];
