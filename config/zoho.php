<?php

return [
    'region' => env('ZOHO_REGION', 'in'),
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'redirect_uri' => env('ZOHO_REDIRECT_URI'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
    'org_id' => env('ZOHO_BILLING_ORG_ID'),
    'base_url' => env('ZOHO_BILLING_BASE_URL', 'https://www.zohoapis.in/billing/v1'),
    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.in/oauth/v2/token'),
    'webhook_token' => env('ZOHO_WEBHOOK_TOKEN'),
];
