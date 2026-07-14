<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Source API
    |--------------------------------------------------------------------------
    |
    | Here you may configure how the migrator talks to the source platform
    | API: how many records are fetched per page and whether SSL certificates
    | must be verified.
    | Disable verification only for local stores using self-signed
    | certificates.
    |
    */

    'per_page' => 100,

    'verify_ssl' => env('MIGRATOR_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Allow local / private hosts
    |--------------------------------------------------------------------------
    |
    | By default the migrator refuses to connect to non-HTTPS URLs or to hosts
    | that resolve to private, loopback or link-local addresses, to prevent the
    | server from being used to reach internal services (SSRF). Enable this only
    | for trusted local development against a store on your own machine/network.
    |
    */

    'allow_local_hosts' => env('MIGRATOR_ALLOW_LOCAL_HOSTS', false),

    /*
    |--------------------------------------------------------------------------
    | Import disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk where the fetched catalog is streamed as a JSONL file
    | before the product import pipeline consumes it. It must be a local disk:
    | the fetcher writes through the disk's absolute path, so cloud drivers
    | (s3, etc.) are not supported here.
    |
    */

    'import_disk' => env('MIGRATOR_IMPORT_DISK', 'local'),

];
