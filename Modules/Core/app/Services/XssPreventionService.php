<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * XssPreventionService: Detect and prevent XSS attacks
 *
 * Provides comprehensive XSS detection and prevention through input validation,
 * HTML sanitization, and dangerous pattern removal. Tracks XSS attempts for
 * security monitoring and incident response.
 *
 * Security Features:
 * - XSS payload detection
 * - HTML sanitization with whitelist
 * - Dangerous tag removal (script, iframe, embed, object)
 * - Event handler removal (onclick, onload, etc.)
 * - JavaScript URL removal (javascript:, data:)
 * - CSS injection prevention
 * - Protocol validation
 * - Attribute whitelisting
 * - Nested tag handling
 * - Encoded payload detection
 */
class XssPreventionService
{
    /**
     * Output encoding service
     */
    private OutputEncodingService $outputEncoding;

    /**
     * HTML Purifier service
     */
    private HtmlPurifierService $htmlPurifier;

    /**
     * Dangerous HTML tags
     */
    private array $dangerousTags = [
        'script', 'iframe', 'embed', 'object', 'applet', 'meta',
        'link', 'style', 'base', 'form', 'input', 'button',
    ];

    /**
     * Dangerous event handlers
     */
    private array $dangerousEventHandlers = [
        'onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout',
        'onmouseenter', 'onmouseleave', 'onkeydown', 'onkeyup',
        'onchange', 'onsubmit', 'onfocus', 'onblur', 'ondblclick',
        'onwheel', 'onscroll', 'onplay', 'onpause', 'onended',
        'oncontextmenu', 'ondrop', 'ondrag', 'onpaste', 'oncopy',
        'oncut', 'onwheel', 'onmove', 'onresize', 'onabort',
    ];

    /**
     * Dangerous protocols
     */
    private array $dangerousProtocols = [
        'javascript', 'data', 'vbscript', 'file', 'about',
    ];

    /**
     * XSS detection patterns
     */
    private array $xssPatterns = [
        // Script tags
        '/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is',
        // Event handlers
        '/on\w+\s*=\s*["\']?[^"\'>\s]+/i',
        // JavaScript URLs
        '/(?:java)?script\s*:/i',
        // Data URLs with HTML/JavaScript
        '/data:text\/(?:html|javascript)/i',
        // VBScript
        '/vbscript\s*:/i',
        // SVG-based XSS
        '/<svg[^>]*>/i',
        // IFrame
        '/<i?frame[^>]*>/i',
        // Embed
        '/<embed[^>]*>/i',
        // Object
        '/<object[^>]*>/i',
        // Form
        '/<form[^>]*>/i',
        // Base tag
        '/<base[^>]*>/i',
        // Meta tag (dangerous for redirects)
        '/<meta[^>]+http-equiv[^>]*>/i',
        // HTML comments with -->
        '/<!--.*?-->/s',
        // Expression in CSS
        '/expression\s*\(/i',
        // Behavior in IE
        '/behavior\s*:/i',
        // Import in CSS
        '/@import/i',
        // URL protocol schemes
        '/(?:java|vb)?script\s*:|data:/i',
        // Encoded payload detection
        '/%3c|%3e|&#60|&#62|&#x3c|&#x3e/i',
    ];

    /**
     * Constructor
     */
    public function __construct(
        OutputEncodingService $outputEncoding,
        HtmlPurifierService $htmlPurifier
    ) {
        $this->outputEncoding = $outputEncoding;
        $this->htmlPurifier = $htmlPurifier;
    }

    /**
     * Detect XSS in input string
     *
     * @param string|null $input Input to check
     * @return bool True if XSS detected
     */
    public function detectXss(?string $input): bool
    {
        if (is_null($input) || $input === '') {
            return false;
        }

        // Check against all patterns
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                Log::warning('XSS pattern detected', [
                    'pattern' => $pattern,
                    'input_length' => strlen($input),
                    'first_100_chars' => substr($input, 0, 100),
                ]);
                return true;
            }
        }

        // Check for null bytes
        if (strpos($input, "\x00") !== false) {
            Log::warning('Null byte detected in input');
            return true;
        }

        // Check for excessive entity encoding (sign of encoded attack)
        if (preg_match_all('/&#\d+;|&#x[0-9a-f]+;|&\w+;/i', $input, $matches)) {
            if (count($matches[0]) > strlen($input) / 4) {
                Log::warning('Excessive entity encoding detected');
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize HTML content
     *
     * @param string|null $html HTML to sanitize
     * @param string $context Context for policy
     * @return string Sanitized HTML
     */
    public function sanitizeHtml(?string $html, string $context = 'general'): string
    {
        if (is_null($html) || $html === '') {
            return '';
        }

        // First pass: remove dangerous tags
        $sanitized = $this->removeDangerousTags($html);

        // Second pass: remove event handlers
        $sanitized = $this->stripEventHandlers($sanitized);

        // Third pass: remove dangerous protocols
        $sanitized = $this->removeDangerousProtocols($sanitized);

        // Fourth pass: use HTML Purifier for final cleanup
        $sanitized = $this->htmlPurifier->purify($sanitized, $context);

        // Log if XSS was detected
        if ($this->detectXss($html)) {
            Log::notice('XSS payload sanitized', [
                'original_length' => strlen($html),
                'sanitized_length' => strlen($sanitized),
                'context' => $context,
            ]);
        }

        return $sanitized;
    }

    /**
     * Remove dangerous HTML tags
     *
     * @param string $html HTML content
     * @return string Cleaned HTML
     */
    public function removeDangerousTags(string $html): string
    {
        // Create pattern for dangerous tags
        $pattern = '<(' . implode('|', $this->dangerousTags) . ')(?:\s[^>]*)?>.*?</\1\s*>|<(' .
                   implode('|', $this->dangerousTags) . ')(?:\s[^>]*)?/?>';

        return preg_replace('/' . $pattern . '/is', '', $html);
    }

    /**
     * Strip event handlers from HTML attributes
     *
     * @param string $html HTML content
     * @return string HTML without event handlers
     */
    public function stripEventHandlers(string $html): string
    {
        $pattern = '/\s*(' . implode('|', $this->dangerousEventHandlers) . ')\s*=\s*["\']?[^"\'>\s]*["\']?/i';
        return preg_replace($pattern, '', $html);
    }

    /**
     * Remove dangerous protocols from URLs
     *
     * @param string $html HTML content
     * @return string HTML with safe protocols
     */
    public function removeDangerousProtocols(string $html): string
    {
        // Pattern for href, src, and similar attributes
        $pattern = '/(?:href|src|action|data|poster|formaction)\s*=\s*["\']?(?:' .
                   implode('|', $this->dangerousProtocols) . '):/i';

        return preg_replace($pattern, '', $html);
    }

    /**
     * Sanitize HTML attributes
     *
     * @param array $attributes Attributes to sanitize
     * @return array Sanitized attributes
     */
    public function sanitizeAttributes(array $attributes): array
    {
        $whitelist = config('output-encoding.safe_attributes', [
            'id', 'class', 'title', 'alt', 'data-*', 'aria-*',
            'href', 'src', 'width', 'height', 'role',
        ]);

        $sanitized = [];

        foreach ($attributes as $name => $value) {
            // Check if attribute matches whitelist
            $allowed = false;

            foreach ($whitelist as $pattern) {
                if ($pattern === $name) {
                    $allowed = true;
                    break;
                }

                // Support wildcards (data-*, aria-*)
                if (strpos($pattern, '*') !== false) {
                    $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                    if (preg_match('/^' . $regex . '$/', $name)) {
                        $allowed = true;
                        break;
                    }
                }
            }

            if (!$allowed) {
                continue;
            }

            // Check for dangerous patterns in attribute value
            if ($this->detectXss($value)) {
                Log::warning('XSS detected in attribute value', [
                    'attribute' => $name,
                ]);
                continue;
            }

            $sanitized[$name] = $this->outputEncoding->encodeAttribute($value);
        }

        return $sanitized;
    }

    /**
     * Check if protocol is safe
     *
     * @param string $url URL to check
     * @return bool True if protocol is safe
     */
    public function isSafeProtocol(string $url): bool
    {
        $url = strtolower(trim($url));

        // Relative URLs are safe
        if (strpos($url, '://') === false && !str_starts_with($url, '//')) {
            return true;
        }

        // Extract protocol
        if (preg_match('/^([a-z]+):/i', $url, $matches)) {
            $protocol = strtolower($matches[1]);

            // Check against dangerous protocols
            if (in_array($protocol, $this->dangerousProtocols, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add custom dangerous pattern
     *
     * @param string $pattern Regex pattern
     */
    public function addDangerousPattern(string $pattern): void
    {
        $this->xssPatterns[] = $pattern;
    }

    /**
     * Add custom dangerous tag
     *
     * @param string $tag Tag name
     */
    public function addDangerousTag(string $tag): void
    {
        $this->dangerousTags[] = strtolower($tag);
    }

    /**
     * Add custom dangerous event handler
     *
     * @param string $handler Handler name
     */
    public function addDangerousEventHandler(string $handler): void
    {
        $this->dangerousEventHandlers[] = strtolower($handler);
    }

    /**
     * Get detected XSS patterns
     *
     * @return array Patterns
     */
    public function getXssPatterns(): array
    {
        return $this->xssPatterns;
    }

    /**
     * Get dangerous tags
     *
     * @return array Tags
     */
    public function getDangerousTags(): array
    {
        return $this->dangerousTags;
    }

    /**
     * Get dangerous event handlers
     *
     * @return array Handlers
     */
    public function getDangerousEventHandlers(): array
    {
        return $this->dangerousEventHandlers;
    }

    /**
     * Get dangerous protocols
     *
     * @return array Protocols
     */
    public function getDangerousProtocols(): array
    {
        return $this->dangerousProtocols;
    }
}
