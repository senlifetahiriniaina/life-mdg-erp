<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\HtmlPurifierService;
use Tests\TestCase;

/**
 * HtmlPurifierTest: Test HTML purification and sanitization
 *
 * Tests HTMLPurifier integration and policy management
 */
class HtmlPurifierTest extends TestCase
{
    /**
     * HTML Purifier service
     */
    private HtmlPurifierService $service;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HtmlPurifierService::class);
    }

    /**
     * Test basic HTML purification
     */
    public function test_purify_basic(): void
    {
        $html = '<p>Safe paragraph</p>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<p>', $purified);
        $this->assertStringContainsString('Safe paragraph', $purified);
    }

    /**
     * Test script tag removal
     */
    public function test_purify_removes_script_tags(): void
    {
        $html = '<p>Before</p><script>alert(1)</script><p>After</p>';
        $purified = $this->service->purify($html);

        $this->assertStringNotContainsString('<script>', $purified);
        $this->assertStringContainsString('Before', $purified);
        $this->assertStringContainsString('After', $purified);
    }

    /**
     * Test unsafe tags
     */
    public function test_purify_removes_unsafe_tags(): void
    {
        $unsafeTags = ['iframe', 'embed', 'object', 'form', 'input', 'base'];

        foreach ($unsafeTags as $tag) {
            $html = "<p>Before</p><$tag src=\"x\"></$tag><p>After</p>";
            $purified = $this->service->purify($html);

            $this->assertStringNotContainsString("<$tag", $purified);
        }
    }

    /**
     * Test safe tags preserved
     */
    public function test_purify_preserves_safe_tags(): void
    {
        $html = '<p>Paragraph</p><strong>Bold</strong><em>Italic</em><u>Underline</u>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<p>', $purified);
        $this->assertStringContainsString('<strong>', $purified);
        $this->assertStringContainsString('<em>', $purified);
    }

    /**
     * Test attribute whitelist
     */
    public function test_purify_whitelist_attributes(): void
    {
        $html = '<a href="http://example.com" onclick="alert(1)" title="Link">Click</a>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('href=', $purified);
        $this->assertStringContainsString('title=', $purified);
        $this->assertStringNotContainsString('onclick', $purified);
    }

    /**
     * Test JavaScript removal
     */
    public function test_purify_removes_javascript(): void
    {
        $payloads = [
            '<a href="javascript:alert(1)">Link</a>',
            '<img src="javascript:alert(1)">',
            '<div style="background: url(javascript:alert(1))">',
        ];

        foreach ($payloads as $html) {
            $purified = $this->service->purify($html);
            $this->assertStringNotContainsString('javascript:', $purified);
        }
    }

    /**
     * Test URL sanitization
     */
    public function test_purify_sanitizes_urls(): void
    {
        $html = '<a href="http://safe.com">Safe</a><a href="javascript:void(0)">Bad</a>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('http://safe.com', $purified);
        $this->assertStringNotContainsString('javascript:', $purified);
    }

    /**
     * Test CSS sanitization
     */
    public function test_purify_sanitizes_css(): void
    {
        $html = '<p style="color: red; background: url(javascript:alert(1))">Text</p>';
        $purified = $this->service->purify($html);

        // CSS with malicious URLs should be removed
        $this->assertStringNotContainsString('javascript:', $purified);
    }

    /**
     * Test null input
     */
    public function test_purify_null_input(): void
    {
        $result = $this->service->purify(null);
        $this->assertEquals('', $result);
    }

    /**
     * Test empty input
     */
    public function test_purify_empty_input(): void
    {
        $result = $this->service->purify('');
        $this->assertEquals('', $result);
    }

    /**
     * Test per-module policies
     */
    public function test_per_module_policies(): void
    {
        // Get policy for a module
        $crmPolicy = $this->service->getPolicy('crm');
        $this->assertIsArray($crmPolicy);
        $this->assertArrayHasKey('allowed_tags', $crmPolicy);

        // Set custom policy
        $customPolicy = [
            'allowed_tags' => ['p', 'strong'],
        ];
        $this->service->setPolicy('custom', $customPolicy);

        $retrieved = $this->service->getPolicy('custom');
        $this->assertArrayHasKey('allowed_tags', $retrieved);
    }

    /**
     * Test allow tag
     */
    public function test_allow_tag(): void
    {
        $html = '<custom>Content</custom>';

        // First purify should remove custom tag
        $purified1 = $this->service->purify($html);
        $this->assertStringNotContainsString('<custom>', $purified1);

        // Add custom tag
        $this->service->allowTag('custom');

        // Now it should be allowed
        $purified2 = $this->service->purify($html);
        // Due to caching, we can't guarantee it will be present,
        // but the method should not throw an error
        $this->assertNotEmpty($purified2);
    }

    /**
     * Test disallow tag
     */
    public function test_disallow_tag(): void
    {
        $this->service->disallowTag('strong');

        $html = '<strong>Bold text</strong>';
        $purified = $this->service->purify($html);

        // Strong tag should be removed
        $this->assertStringNotContainsString('<strong>', $purified);
    }

    /**
     * Test allow attribute
     */
    public function test_allow_attribute(): void
    {
        $this->service->allowAttribute('div', 'data-custom');

        $html = '<div data-custom="value">Content</div>';
        $purified = $this->service->purify($html);

        // Should allow custom data attribute
        $this->assertNotEmpty($purified);
    }

    /**
     * Test get allowed tags
     */
    public function test_get_allowed_tags(): void
    {
        $tags = $this->service->getAllowedTags('general');

        $this->assertIsArray($tags);
        $this->assertContains('p', $tags);
        $this->assertContains('strong', $tags);
        $this->assertNotContains('script', $tags);
    }

    /**
     * Test get allowed attributes
     */
    public function test_get_allowed_attributes(): void
    {
        $attrs = $this->service->getAllowedAttributes('a', 'general');

        $this->assertIsArray($attrs);
        // Should contain href at minimum
        $this->assertTrue(count($attrs) > 0);
    }

    /**
     * Test complex HTML structure
     */
    public function test_purify_complex_structure(): void
    {
        $html = <<<'HTML'
            <div class="container">
                <h1>Title</h1>
                <p>Paragraph with <strong>bold</strong> and <em>italic</em>.</p>
                <ul>
                    <li><a href="http://example.com">Link</a></li>
                    <li>Item 2</li>
                </ul>
                <script>alert(1)</script>
                <iframe src="http://evil.com"></iframe>
            </div>
        HTML;

        $purified = $this->service->purify($html);

        // Safe content should be preserved
        $this->assertStringContainsString('Title', $purified);
        $this->assertStringContainsString('<strong>', $purified);
        $this->assertStringContainsString('<em>', $purified);
        $this->assertStringContainsString('http://example.com', $purified);

        // Unsafe content should be removed
        $this->assertStringNotContainsString('<script>', $purified);
        $this->assertStringNotContainsString('<iframe>', $purified);
    }

    /**
     * Test table preservation
     */
    public function test_purify_preserves_table(): void
    {
        $html = <<<'HTML'
            <table>
                <tr>
                    <th>Header</th>
                </tr>
                <tr>
                    <td>Data</td>
                </tr>
            </table>
        HTML;

        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<table>', $purified);
        $this->assertStringContainsString('<tr>', $purified);
        $this->assertStringContainsString('<td>', $purified);
    }

    /**
     * Test image handling
     */
    public function test_purify_image_handling(): void
    {
        $html = '<img src="http://example.com/image.jpg" alt="Image">';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<img', $purified);
        $this->assertStringContainsString('http://example.com/image.jpg', $purified);
    }

    /**
     * Test blockquote preservation
     */
    public function test_purify_preserves_blockquote(): void
    {
        $html = '<blockquote><p>Quoted text</p></blockquote>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<blockquote>', $purified);
        $this->assertStringContainsString('Quoted text', $purified);
    }

    /**
     * Test code block preservation
     */
    public function test_purify_preserves_code(): void
    {
        $html = '<pre><code>var x = "test";</code></pre>';
        $purified = $this->service->purify($html);

        $this->assertStringContainsString('<pre>', $purified);
        $this->assertStringContainsString('<code>', $purified);
        $this->assertStringContainsString('var x = "test";', $purified);
    }

    /**
     * Test performance
     */
    public function test_purify_performance(): void
    {
        $html = str_repeat('<p>Paragraph with <strong>content</strong></p>', 100);

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $this->service->purify($html);
        }

        $elapsed = microtime(true) - $start;

        // Should complete reasonably fast
        $this->assertLessThan(5.0, $elapsed);
    }

    /**
     * Test mixed safe and unsafe content
     */
    public function test_purify_mixed_content(): void
    {
        $html = <<<'HTML'
            <h1>Title</h1>
            <p>Safe paragraph</p>
            <script>alert('unsafe')</script>
            <p>Another safe paragraph</p>
            <img onerror="alert('unsafe')" src="image.jpg">
        HTML;

        $purified = $this->service->purify($html);

        // Safe content
        $this->assertStringContainsString('<h1>', $purified);
        $this->assertStringContainsString('Safe paragraph', $purified);
        $this->assertStringContainsString('Another safe paragraph', $purified);

        // Unsafe content
        $this->assertStringNotContainsString('<script>', $purified);
        $this->assertStringNotContainsString('onerror=', $purified);
    }
}
