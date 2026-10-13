<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access token signing secret
    |--------------------------------------------------------------------------
    |
    | HS256 key used to sign and verify the JWT access tokens. Required: the
    | API refuses to issue or verify tokens when it is empty.
    |
    */

    'jwt_secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Initial admin credentials
    |--------------------------------------------------------------------------
    |
    | Read at container start by `library:ensure-admin` (plan D-10).
    |
    */

    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),

];
