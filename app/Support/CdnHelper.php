<?php

namespace App\Support;

class CdnHelper
{
    /**
     * Get CDN URL for an asset path
     *
     * @param string $path Asset path relative to public directory
     * @return string Full CDN or local URL
     */
    public static function url(string $path): string
    {
        $cdnUrl = config('cdn.url');
        $cdnEnabled = config('cdn.enabled');

        // Ensure path starts with /
        $path = ltrim($path, '/');

        if (!$cdnEnabled || !$cdnUrl) {
            return "/{$path}";
        }

        // Remove trailing slash from CDN URL
        $cdnUrl = rtrim($cdnUrl, '/');

        return "{$cdnUrl}/{$path}";
    }

    /**
     * Check if CDN is enabled
     */
    public static function isEnabled(): bool
    {
        return config('cdn.enabled') && config('cdn.url');
    }

    /**
     * Get CDN provider name
     */
    public static function provider(): string
    {
        return config('cdn.provider', 'none');
    }

    /**
     * Check if asset should be served via CDN
     */
    public static function shouldServeByCdn(string $path): bool
    {
        if (!static::isEnabled()) {
            return false;
        }

        $assetPaths = config('cdn.asset_paths', []);
        $extensions = config('cdn.asset_extensions', []);

        // Check if path matches CDN asset paths
        foreach ($assetPaths as $assetPath) {
            if (strpos($path, "/{$assetPath}/") === 0 || strpos($path, $assetPath . '/') === 0) {
                return true;
            }
        }

        // Check file extension
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), $extensions);
    }
}
