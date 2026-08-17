<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\XssPreventionService;
use Modules\Core\Services\OutputEncodingService;
use Modules\Core\Services\HtmlPurifierService;
use Tests\TestCase;

/**
 * XssPreventionTest: Test XSS detection and prevention
 *
 * Tests XSS payload detection, sanitization, and protection
 */
class XssPreventionTest extends TestCase
{
    /**
     * XSS Prevention service
     */
    private XssPreventionService $service;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(XssPreventionService::class);
    }

    /**
     * Test script tag detection
     */
    public function test_script_tag_detected(): void
    {
        $payload = '<script>alert("XSS")</script>';
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test script tag removed
     */
    public function test_script_tag_removed(): void
    {
        $html = '<p>Safe content</p><script>alert("XSS")</script><p>More safe</p>';
        $sanitized = $this->service->sanitizeHtml($html);

        $this->assertStringContainsString('Safe content', $sanitized);
        $this->assertStringContainsString('More safe', $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    /**
     * Test event handlers detected
     */
    public function test_event_handlers_detected(): void
    {
        $payload = '<img src=x onclick="alert(1)">';
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test event handlers removed
     */
    public function test_event_handlers_removed(): void
    {
        $html = '<div onclick="alert(1)">Click me</div>';
        $sanitized = $this->service->stripEventHandlers($html);

        $this->assertStringContainsString('Click me', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
    }

    /**
     * Test multiple event handlers removed
     */
    public function test_multiple_event_handlers_removed(): void
    {
        $html = '<img onload="alert(1)" onerror="alert(2)" onmouseover="alert(3)">';
        $sanitized = $this->service->stripEventHandlers($html);

        $this->assertStringNotContainsString('onload', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('onmouseover', $sanitized);
    }

    /**
     * Test JavaScript URL detected
     */
    public function test_javascript_url_detected(): void
    {
        $payload = '<a href="javascript:alert(1)">Click</a>';
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test JavaScript URL removed
     */
    public function test_javascript_url_removed(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $sanitized = $this->service->removeDangerousProtocols($html);

        $this->assertStringNotContainsString('javascript:', $sanitized);
    }

    /**
     * Test data URL detected
     */
    public function test_data_url_detected(): void
    {
        $payload = '<img src="data:text/html,<script>alert(1)</script>">';
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test iframe blocked
     */
    public function test_iframe_blocked(): void
    {
        $html = '<iframe src="http://evil.com"></iframe>';
        $sanitized = $this->service->removeDangerousTags($html);

        $this->assertStringNotContainsString('iframe', $sanitized);
    }

    /**
     * Test embed blocked
     */
    public function test_embed_blocked(): void
    {
        $html = '<embed src="http://evil.com/malware.swf">';
        $sanitized = $this->service->removeDangerousTags($html);

        $this->assertStringNotContainsString('embed', $sanitized);
    }

    /**
     * Test object blocked
     */
    public function test_object_blocked(): void
    {
        $html = '<object data="http://evil.com/malware.swf"></object>';
        $sanitized = $this->service->removeDangerousTags($html);

        $this->assertStringNotContainsString('object', $sanitized);
    }

    /**
     * Test safe HTML preserved
     */
    public function test_safe_html_preserved(): void
    {
        $html = '<p>This is <strong>safe</strong> HTML content</p>';
        $sanitized = $this->service->sanitizeHtml($html);

        $this->assertStringContainsString('<p>', $sanitized);
        $this->assertStringContainsString('<strong>', $sanitized);
        $this->assertStringContainsString('safe', $sanitized);
    }

    /**
     * Test attribute sanitization
     */
    public function test_attribute_sanitization(): void
    {
        $attributes = [
            'href' => 'http://safe.com',
            'onclick' => 'alert(1)',
            'title' => 'Safe title',
            'javascript' => 'bad',
        ];

        $sanitized = $this->service->sanitizeAttributes($attributes);

        $this->assertArrayHasKey('href', $sanitized);
        $this->assertArrayHasKey('title', $sanitized);
        // onclick should be removed
        $this->assertArrayNotHasKey('onclick', $sanitized);
    }

    /**
     * Test CSS sanitization
     */
    public function test_css_sanitization(): void
    {
        $html = '<div style="background: url(javascript:alert(1))">Content</div>';
        $sanitized = $this->service->sanitizeHtml($html);

        $this->assertStringNotContainsString('javascript:', $sanitized);
    }

    /**
     * Test protocol validation
     */
    public function test_protocol_validation(): void
    {
        $this->assertTrue($this->service->isSafeProtocol('http://example.com'));
        $this->assertTrue($this->service->isSafeProtocol('https://example.com'));
        $this->assertTrue($this->service->isSafeProtocol('mailto:test@example.com'));
        $this->assertTrue($this->service->isSafeProtocol('/relative/path'));

        $this->assertFalse($this->service->isSafeProtocol('javascript:alert(1)'));
        $this->assertFalse($this->service->isSafeProtocol('data:text/html,<script>'));
        $this->assertFalse($this->service->isSafeProtocol('vbscript:msgbox(1)'));
    }

    /**
     * Test nested tags
     */
    public function test_nested_tags(): void
    {
        $html = '<div><p><span><script>alert(1)</script></span></p></div>';
        $sanitized = $this->service->sanitizeHtml($html);

        $this->assertStringContainsString('<div>', $sanitized);
        $this->assertStringContainsString('<p>', $sanitized);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    /**
     * Test encoded XSS detected
     */
    public function test_encoded_xss_detected(): void
    {
        $payloads = [
            '&#60;script&#62;',
            '&#x3c;script&#x3e;',
            '%3cscript%3e',
        ];

        foreach ($payloads as $payload) {
            $this->assertTrue($this->service->detectXss($payload));
        }
    }

    /**
     * Test null byte injection
     */
    public function test_null_byte_injection_detected(): void
    {
        $payload = "safe\x00<script>alert(1)</script>";
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test XSS patterns can be extended
     */
    public function test_custom_dangerous_pattern(): void
    {
        $this->service->addDangerousPattern('/<customtag>/i');

        $payload = '<customtag>content</customtag>';
        $this->assertTrue($this->service->detectXss($payload));
    }

    /**
     * Test dangerous tags can be extended
     */
    public function test_custom_dangerous_tag(): void
    {
        $this->service->addDangerousTag('customtag');

        $html = '<customtag>content</customtag>';
        $sanitized = $this->service->removeDangerousTags($html);

        $this->assertStringNotContainsString('customtag', $sanitized);
    }

    /**
     * Test complex XSS payload
     */
    public function test_complex_xss_payload(): void
    {
        $payloads = [
            '<svg onload="alert(1)">',
            '<img src=x onerror="fetch(\'http://evil.com\')">',
            '<iframe srcdoc="<script>alert(1)</script>">',
            '<details open ontoggle="alert(1)">',
        ];

        foreach ($payloads as $payload) {
            $this->assertTrue($this->service->detectXss($payload));
        }
    }

    /**
     * Test safe content not blocked
     */
    public function test_safe_content_not_blocked(): void
    {
        $safeContent = [
            'Hello World',
            '<p>Paragraph</p>',
            '<strong>Bold</strong> and <em>italic</em>',
            'Normal text with special chars: !@#$%',
        ];

        foreach ($safeContent as $content) {
            $this->assertFalse($this->service->detectXss($content));
        }
    }

    /**
     * Test XSS in attributes
     */
    public function test_xss_in_attributes(): void
    {
        $html = '<a href="javascript:alert(1)">Link</a>';
        $this->assertTrue($this->service->detectXss($html));
    }

    /**
     * Test style-based XSS
     */
    public function test_style_based_xss(): void
    {
        $payloads = [
            '<div style="background: url(javascript:alert(1))">',
            '<div style="background: url(data:text/html,<script>)">',
            '<div style="expression(alert(1))">',
        ];

        foreach ($payloads as $payload) {
            $this->assertTrue($this->service->detectXss($payload));
        }
    }

    /**
     * Test case-insensitive detection
     */
    public function test_case_insensitive_detection(): void
    {
        $payloads = [
            '<SCRIPT>alert(1)</SCRIPT>',
            '<Script>alert(1)</Script>',
            // <onclick>alert(1)</onclick> (event-handler NAME used as a tag,
            // no on...= attribute) is not real exploit syntax — browsers never
            // execute it, so it isn't a detection gap. A real event-handler
            // attribute is the case-insensitive vector actually worth testing.
            '<div ONCLICK="alert(1)">x</div>',
        ];

        foreach ($payloads as $payload) {
            $this->assertTrue($this->service->detectXss($payload));
        }
    }
}
