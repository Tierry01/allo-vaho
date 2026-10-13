<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Back-office contact and legal links
    |--------------------------------------------------------------------------
    |
    | Shown on the sign-in pages of the back-office. A link whose value is not
    | configured is hidden: nothing is invented.
    |
    */

    'support_email' => env('ADMIN_SUPPORT_EMAIL'),

    'privacy_url' => env('ADMIN_PRIVACY_URL'),

    'version' => env('ADMIN_APP_VERSION'),

];
