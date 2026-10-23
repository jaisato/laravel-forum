<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The proxies whose X-Forwarded-For, -Proto and -Port headers give the
    | client's address and scheme (bootstrap/app.php): a comma-separated list
    | of addresses or CIDR ranges. In production the forum runs behind Caddy
    | on the Docker networks (compose.prod.yaml). Empty, nobody is trusted.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
