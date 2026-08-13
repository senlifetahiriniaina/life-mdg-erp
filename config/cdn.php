<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CDN provider for serving static assets from edge locations
    | globally, reducing TTFB and bandwidth usage from origin server.
    |
    | Supported providers: 'cloudflare', 'aws', 'nginx', 'none'
    |
    */

    'enabled' => env('CDN_ENABLED', false),

    'url' => env('CDN_URL', ''),

    'provider' => env('CDN_PROVIDER', 'cloudflare'),

    /*
    |--------------------------------------------------------------------------
    | Asset Settings
    |--------------------------------------------------------------------------
    |
    | Configure which assets are served through CDN
    |
    */

    'asset_paths' => [
        'build',      // Vite build output
        'fonts',      // Self-hosted fonts
        'images',     // Optimized images
    ],

    'asset_extensions' => [
        'css', 'js', 'woff2', 'woff', 'ttf', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | Define cache TTL for different asset types (in seconds)
    |
    */

    'cache_ttl' => [
        'assets'   => 31536000,  // 1 year for /build/* (immutable hashed filenames)
        'fonts'    => 31536000,  // 1 year for fonts
        'images'   => 2592000,   // 30 days for images
        'html'     => 3600,      // 1 hour for HTML pages
        'api'      => 0,         // Never cache API responses
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Configuration
    |--------------------------------------------------------------------------
    |
    | Required for Cloudflare CDN provider
    |
    */

    'cloudflare' => [
        'zone_id'   => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'domain'    => env('CLOUDFLARE_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS CloudFront Configuration
    |--------------------------------------------------------------------------
    |
    | Required for AWS CloudFront CDN provider
    |
    */

    'aws' => [
        'distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID'),
        'region'          => env('AWS_REGION', 'us-east-1'),
        's3_bucket'       => env('AWS_S3_BUCKET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nginx Reverse Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | For local testing or on-premises CDN solution
    |
    */

    'nginx' => [
        'listen_port' => env('NGINX_CDN_PORT', 8080),
        'cache_path'  => env('NGINX_CACHE_PATH', '/var/cache/nginx'),
    ],
];
