<?php
return [
    #PAYMOB
    'PAYMOB_API_KEY' => env('PAYMOB_API_KEY'),
    'PAYMOB_API_SECRET'  => env('PAYMOB_API_SECRET'),
    'PAYMOB_INTEGRATION_ID' => env('PAYMOB_INTEGRATION_ID'),
    'PAYMOB_IFRAME_ID' => env('PAYMOB_IFRAME_ID'),
    'PAYMOB_HMAC' => env('PAYMOB_HMAC'),
    'PAYMOB_CURRENCY'=> env('PAYMOB_CURRENCY',"EGP"),

    #PAYMOB_WALLET (vodaphone-cash,orange-money,etisalat-cash,we-cash,meza-wallet) - test phone 01010101010 ,PIN & OTP IS 123456
    'PAYMOB_WALLET_INTEGRATION_ID'=>env('PAYMOB_WALLET_INTEGRATION_ID'),

    #Stripe
    'STRIPE_API_PUBLISHED_KEY' => env('STRIPE_API_PUBLISHED_KEY'),
    'STRIPE_API_SECRET' => env('STRIPE_API_SECRET'),
    'STRIPE_WEBHOOK_SECRET' => env('STRIPE_WEBHOOK_SECRET'),
    'STRIPE_BASE_URL' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
    'STRIPE_CURRENCY' => env('STRIPE_CURRENCY'),
];