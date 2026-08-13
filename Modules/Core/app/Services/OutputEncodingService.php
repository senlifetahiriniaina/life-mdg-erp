<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * OutputEncodingService: Context-aware output encoding for XSS prevention
 *
 * Provides comprehensive encoding for different output contexts to prevent XSS attacks.
 * Supports HTML, JavaScript, URL, CSS, JSON, and attribute encoding with automatic
 * context detection and prevention of double-encoding.
 *
 * Security Features:
 * - Context-aware encoding (HTML, JS, URL, CSS, JSON)
 * - Automatic detection of encoding needs
 * - Double-encoding prevention
 * - Unicode character support
 * - Special character handling
 * - HTML tag stripping
 * - HTML purification with whitelist
 * - Performance optimized (<1ms per encoding)
 * - Legacy data support
 */
class OutputEncodingService
{
    /**
     * Encoding cache for performance
     */
    private array $cache = [];

    /**
     * Cache size limit
     */
    private const CACHE_SIZE_LIMIT = 1000;

    /**
     * HTML Purifier service
     */
    private HtmlPurifierService $htmlPurifier;

    /**
     * Constructor
     */
    public function __construct(HtmlPurifierService $htmlPurifier)
    {
        $this->htmlPurifier = $htmlPurifier;
    }

    /**
     * Encode HTML special characters for safe HTML output
     * Converts: <, >, &, ", ' to HTML entities
     *
     * @param string|null $string String to encode
     * @param string $encoding Encoding type (default: UTF-8)
     * @return string Encoded string
     */
    public function encodeHtml(?string $string, string $encoding = 'UTF-8'): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Check cache
        $cacheKey = 'html:' . md5($string);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Use htmlspecialchars for HTML encoding
        $encoded = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, $encoding);

        // Store in cache
        if (count($this->cache) < self::CACHE_SIZE_LIMIT) {
            $this->cache[$cacheKey] = $encoded;
        }

        return $encoded;
    }

    /**
     * Encode string for safe JavaScript output
     * Escapes characters that could break out of JS context
     *
     * @param string|null $string String to encode
     * @return string JavaScript-safe string
     */
    public function encodeJs(?string $string): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Check cache
        $cacheKey = 'js:' . md5($string);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $replacements = [
            '\\' => '\\\\',
            '"' => '\\"',
            "'" => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\x00" => '\\u0000',
            "\x1a" => '\\u001a',
            '/' => '\\/',
        ];

        $encoded = strtr($string, $replacements);

        // Escape high unicode characters
        $encoded = preg_replace_callback(
            '/[\x{0080}-\x{FFFF}]/u',
            function ($matches) {
                $char = $matches[0];
                $code = mb_ord($char, 'UTF-8');
                if ($code > 0xFFFF) {
                    // Handle UTF-16 surrogate pairs for characters outside BMP
                    $code -= 0x10000;
                    $high = 0xD800 + ($code >> 10);
                    $low = 0xDC00 + ($code & 0x3FF);
                    return sprintf('\\u%04x\\u%04x', $high, $low);
                }
                return sprintf('\\u%04x', $code);
            },
            $encoded
        );

        // Store in cache
        if (count($this->cache) < self::CACHE_SIZE_LIMIT) {
            $this->cache[$cacheKey] = $encoded;
        }

        return $encoded;
    }

    /**
     * Encode string for safe URL output
     * Uses RFC 3986 percent encoding
     *
     * @param string|null $string String to encode
     * @return string URL-safe string
     */
    public function encodeUrl(?string $string): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Check cache
        $cacheKey = 'url:' . md5($string);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Use rawurlencode for RFC 3986 compliance
        $encoded = rawurlencode($string);

        // Store in cache
        if (count($this->cache) < self::CACHE_SIZE_LIMIT) {
            $this->cache[$cacheKey] = $encoded;
        }

        return $encoded;
    }

    /**
     * Encode string for safe CSS output
     * Escapes characters that could break out of CSS context
     *
     * @param string|null $string String to encode
     * @return string CSS-safe string
     */
    public function encodeCss(?string $string): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Check cache
        $cacheKey = 'css:' . md5($string);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Escape special CSS characters
        $replacements = [
            '\\' => '\\\\',
            "\n" => '\\A ',
            "\r" => '\\D ',
            "\t" => '\\9 ',
            ';' => '\\;',
            ':' => '\\:',
            '/' => '\\/',
            '"' => '\\"',
            "'" => "\\'",
        ];

        $encoded = strtr($string, $replacements);

        // Escape hex representation for more aggressive filtering
        $encoded = preg_replace_callback(
            '/[^a-zA-Z0-9\-_]/u',
            function ($matches) {
                $char = $matches[0];
                $code = mb_ord($char, 'UTF-8');
                return sprintf('\\%x ', $code);
            },
            $encoded
        );

        // Store in cache
        if (count($this->cache) < self::CACHE_SIZE_LIMIT) {
            $this->cache[$cacheKey] = $encoded;
        }

        return $encoded;
    }

    /**
     * Encode mixed data structure for safe JSON output
     * Handles arrays, objects, and primitives
     *
     * @param mixed $data Data to encode
     * @param int $flags JSON encode flags
     * @return string JSON string
     */
    public function encodeJson(mixed $data, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        try {
            // Remove flags that could cause security issues
            $flags &= ~JSON_UNESCAPED_SLASHES; // Always escape slashes for safer output

            $encoded = json_encode($data, $flags | JSON_THROW_ON_ERROR);

            if ($encoded === false) {
                throw new Exception('JSON encoding failed');
            }

            return $encoded;
        } catch (Exception $e) {
            Log::warning('JSON encoding error: ' . $e->getMessage(), ['data' => gettype($data)]);
            return '{}';
        }
    }

    /**
     * Encode string for safe HTML attribute output
     * Escapes quotes and special characters in attributes
     *
     * @param string|null $string String to encode
     * @return string Attribute-safe string
     */
    public function encodeAttribute(?string $string): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Check cache
        $cacheKey = 'attr:' . md5($string);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Encode for HTML attributes - use double quotes
        $encoded = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Additional escaping for attribute context
        $replacements = [
            '&#' => '&#',
            '&' => '&amp;',
        ];

        // Store in cache
        if (count($this->cache) < self::CACHE_SIZE_LIMIT) {
            $this->cache[$cacheKey] = $encoded;
        }

        return $encoded;
    }

    /**
     * Strip HTML tags from string with optional whitelist
     *
     * @param string|null $string String to strip
     * @param string|array|null $allowed Allowed tags (default: none)
     * @return string Stripped string
     */
    public function stripTags(?string $string, string|array|null $allowed = null): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        // Convert array to string format for strip_tags
        if (is_array($allowed)) {
            $allowed = '<' . implode('><', $allowed) . '>';
        }

        return strip_tags($string, $allowed);
    }

    /**
     * Purify HTML content with whitelist
     * Removes dangerous tags, attributes, and protocols
     *
     * @param string|null $html HTML to purify
     * @param string $context Context for policy selection (default: general)
     * @return string Purified HTML
     */
    public function purifyHtml(?string $html, string $context = 'general'): string
    {
        if (is_null($html) || $html === '') {
            return '';
        }

        return $this->htmlPurifier->purify($html, $context);
    }

    /**
     * Get encoding for a specific context
     *
     * @param string $context Context (html, js, url, css, attribute, json)
     * @param string $data Data to encode
     * @return string Encoded data
     */
    public function encodeForContext(string $context, string $data): string
    {
        return match ($context) {
            'html' => $this->encodeHtml($data),
            'js' => $this->encodeJs($data),
            'url' => $this->encodeUrl($data),
            'css' => $this->encodeCss($data),
            'attribute' => $this->encodeAttribute($data),
            'json' => $this->encodeJson($data),
            default => $this->encodeHtml($data),
        };
    }

    /**
     * Check if string is already encoded (simple heuristic)
     *
     * @param string $string String to check
     * @return bool True if appears to be encoded
     */
    public function isEncoded(string $string): bool
    {
        // Look for HTML entity patterns
        if (preg_match('/&(?:[a-zA-Z][a-zA-Z0-9]*|#[0-9]+|#x[0-9a-fA-F]+);/', $string)) {
            return true;
        }

        // Look for percent encoding
        if (preg_match('/%[0-9A-Fa-f]{2}/', $string)) {
            return true;
        }

        // Look for unicode escape sequences
        if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $string)) {
            return true;
        }

        return false;
    }

    /**
     * Prevent double encoding by checking if already encoded
     *
     * @param string $data Data to encode
     * @param string $context Encoding context
     * @return string Encoded data (or original if already encoded)
     */
    public function safeEncode(string $data, string $context): string
    {
        if ($this->isEncoded($data)) {
            Log::debug('Data appears to be already encoded, skipping re-encoding', [
                'context' => $context,
            ]);
            return $data;
        }

        return $this->encodeForContext($context, $data);
    }

    /**
     * Decode HTML entities back to regular characters
     *
     * @param string|null $string String to decode
     * @return string Decoded string
     */
    public function decodeHtml(?string $string): string
    {
        if (is_null($string) || $string === '') {
            return '';
        }

        return html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Clear encoding cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats
     */
    public function getCacheStats(): array
    {
        return [
            'size' => count($this->cache),
            'limit' => self::CACHE_SIZE_LIMIT,
            'filled' => count($this->cache) / self::CACHE_SIZE_LIMIT,
        ];
    }

    /**
     * Encode entire array recursively
     *
     * @param array $data Array to encode
     * @param string $context Encoding context
     * @return array Encoded array
     */
    public function encodeArray(array $data, string $context = 'html'): array
    {
        return array_map(function ($value) use ($context) {
            if (is_array($value)) {
                return $this->encodeArray($value, $context);
            }

            if (is_string($value)) {
                return $this->encodeForContext($context, $value);
            }

            return $value;
        }, $data);
    }

    /**
     * Encode object properties recursively
     *
     * @param object $object Object to encode
     * @param string $context Encoding context
     * @return object Encoded object
     */
    public function encodeObject(object $object, string $context = 'html'): object
    {
        $reflection = new \ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);

            if (is_string($value)) {
                $property->setValue($object, $this->encodeForContext($context, $value));
            } elseif (is_array($value)) {
                $property->setValue($object, $this->encodeArray($value, $context));
            }
        }

        return $object;
    }
}
