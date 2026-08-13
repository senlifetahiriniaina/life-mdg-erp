<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Modules\Core\Services\OutputEncodingService;
use Modules\Core\Services\HtmlPurifierService;
use Tests\TestCase;

/**
 * OutputEncodingTest: Test output encoding for XSS prevention
 *
 * Tests HTML, JavaScript, URL, CSS, JSON, and attribute encoding
 */
class OutputEncodingTest extends TestCase
{
    /**
     * Output encoding service
     */
    private OutputEncodingService $service;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $htmlPurifier = app(HtmlPurifierService::class);
        $this->service = app(OutputEncodingService::class);
    }

    /**
     * Test HTML encoding
     */
    public function test_html_encoding(): void
    {
        $input = '<script>alert("XSS")</script>';
        $output = $this->service->encodeHtml($input);

        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test HTML encoding with special characters
     */
    public function test_html_encoding_special_chars(): void
    {
        $input = 'Hello & goodbye "quoted" \'single\'';
        $output = $this->service->encodeHtml($input);

        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&quot;', $output);
        $this->assertStringContainsString('&#039;', $output);
    }

    /**
     * Test JavaScript encoding
     */
    public function test_js_encoding(): void
    {
        $input = 'var x = "value"; alert(\'test\');';
        $output = $this->service->encodeJs($input);

        $this->assertStringContainsString('\\"', $output);
        $this->assertStringContainsString("\\'", $output);
    }

    /**
     * Test JavaScript encoding with newlines
     */
    public function test_js_encoding_newlines(): void
    {
        $input = "line1\nline2\rline3";
        $output = $this->service->encodeJs($input);

        $this->assertStringContainsString('\\n', $output);
        $this->assertStringContainsString('\\r', $output);
    }

    /**
     * Test JavaScript encoding with null bytes
     */
    public function test_js_encoding_null_bytes(): void
    {
        $input = "test\x00payload";
        $output = $this->service->encodeJs($input);

        $this->assertStringContainsString('\\u0000', $output);
    }

    /**
     * Test URL encoding
     */
    public function test_url_encoding(): void
    {
        $input = 'hello world & special<>chars';
        $output = $this->service->encodeUrl($input);

        $this->assertStringContainsString('%20', $output);
        $this->assertStringContainsString('%26', $output);
        $this->assertStringContainsString('%3C', $output);
        $this->assertStringContainsString('%3E', $output);
    }

    /**
     * Test CSS encoding
     */
    public function test_css_encoding(): void
    {
        $input = 'color: red; background: url("javascript:alert(1)")';
        $output = $this->service->encodeCss($input);

        $this->assertStringContainsString('\\;', $output);
    }

    /**
     * Test JSON encoding
     */
    public function test_json_encoding(): void
    {
        $data = ['key' => 'value', 'number' => 123, 'bool' => true];
        $output = $this->service->encodeJson($data);

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(123, $decoded['number']);
        $this->assertTrue($decoded['bool']);
    }

    /**
     * Test JSON encoding with special characters
     */
    public function test_json_encoding_special_chars(): void
    {
        $data = ['html' => '<tag>', 'quotes' => '"quoted"'];
        $output = $this->service->encodeJson($data);

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals('<tag>', $decoded['html']);
        $this->assertEquals('"quoted"', $decoded['quotes']);
    }

    /**
     * Test attribute encoding
     */
    public function test_attribute_encoding(): void
    {
        $input = 'value" onload="alert(1)';
        $output = $this->service->encodeAttribute($input);

        $this->assertStringContainsString('&quot;', $output);
    }

    /**
     * Test context-aware encoding
     */
    public function test_context_aware_encoding(): void
    {
        $input = '<script>alert("XSS")</script>';

        $htmlEncoded = $this->service->encodeForContext('html', $input);
        $this->assertStringContainsString('&lt;', $htmlEncoded);

        $urlEncoded = $this->service->encodeForContext('url', $input);
        $this->assertStringContainsString('%3C', $urlEncoded);
    }

    /**
     * Test strip tags
     */
    public function test_strip_tags(): void
    {
        $input = '<p>Keep this</p><script>Remove this</script>';
        $output = $this->service->stripTags($input, ['p']);

        $this->assertStringContainsString('<p>', $output);
        $this->assertStringNotContainsString('<script>', $output);
    }

    /**
     * Test null input handling
     */
    public function test_null_encoding(): void
    {
        $this->assertEquals('', $this->service->encodeHtml(null));
        $this->assertEquals('', $this->service->encodeJs(null));
        $this->assertEquals('', $this->service->encodeUrl(null));
        $this->assertEquals('', $this->service->encodeCss(null));
        $this->assertEquals('', $this->service->encodeAttribute(null));
    }

    /**
     * Test empty string handling
     */
    public function test_empty_string_encoding(): void
    {
        $this->assertEquals('', $this->service->encodeHtml(''));
        $this->assertEquals('', $this->service->encodeJs(''));
        $this->assertEquals('', $this->service->encodeUrl(''));
        $this->assertEquals('', $this->service->encodeCss(''));
        $this->assertEquals('', $this->service->encodeAttribute(''));
    }

    /**
     * Test double encoding prevention
     */
    public function test_double_encoding_prevention(): void
    {
        $original = '<tag>';
        $encoded = $this->service->encodeHtml($original);

        // Check if encoded
        $this->assertTrue($this->service->isEncoded($encoded));

        // Safe encode should detect it's already encoded
        $safeEncoded = $this->service->safeEncode($encoded, 'html');
        $this->assertEquals($encoded, $safeEncoded);
    }

    /**
     * Test unicode character encoding
     */
    public function test_unicode_encoding(): void
    {
        $input = 'Hello 世界 مرحبا мир';
        $htmlEncoded = $this->service->encodeHtml($input);

        // Should preserve unicode characters
        $this->assertStringContainsString('世界', $htmlEncoded);
        $this->assertStringContainsString('مرحبا', $htmlEncoded);
        $this->assertStringContainsString('мир', $htmlEncoded);
    }

    /**
     * Test encoding array recursively
     */
    public function test_encode_array_recursive(): void
    {
        $data = [
            'name' => '<script>alert(1)</script>',
            'nested' => [
                'value' => '<img src=x onerror="alert(1)">',
            ],
        ];

        $encoded = $this->service->encodeArray($data, 'html');

        $this->assertStringContainsString('&lt;', $encoded['name']);
        $this->assertStringContainsString('&lt;', $encoded['nested']['value']);
    }

    /**
     * Test performance - encoding should be fast
     */
    public function test_performance(): void
    {
        $input = str_repeat('Hello World <>&"\'', 100);
        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $this->service->encodeHtml($input);
        }

        $elapsed = microtime(true) - $start;

        // Should complete 100 iterations in less than 1 second
        $this->assertLessThan(1.0, $elapsed);
    }

    /**
     * Test cache statistics
     */
    public function test_cache_stats(): void
    {
        $this->service->encodeHtml('test1');
        $this->service->encodeHtml('test2');

        $stats = $this->service->getCacheStats();

        $this->assertArrayHasKey('size', $stats);
        $this->assertArrayHasKey('limit', $stats);
        $this->assertGreaterThan(0, $stats['size']);
    }

    /**
     * Test cache clearing
     */
    public function test_cache_clearing(): void
    {
        $this->service->encodeHtml('test');

        $stats = $this->service->getCacheStats();
        $this->assertGreaterThan(0, $stats['size']);

        $this->service->clearCache();

        $stats = $this->service->getCacheStats();
        $this->assertEquals(0, $stats['size']);
    }

    /**
     * Test decode HTML
     */
    public function test_decode_html(): void
    {
        $encoded = $this->service->encodeHtml('<tag>');
        $decoded = $this->service->decodeHtml($encoded);

        $this->assertEquals('<tag>', $decoded);
    }

    /**
     * Test mixed content encoding
     */
    public function test_mixed_content_encoding(): void
    {
        $input = 'Hello <script>alert("XSS")</script> World';
        $encoded = $this->service->encodeHtml($input);

        $this->assertStringContainsString('Hello', $encoded);
        $this->assertStringContainsString('World', $encoded);
        $this->assertStringContainsString('&lt;script&gt;', $encoded);
    }

    /**
     * Test special characters in different contexts
     */
    public function test_special_characters(): void
    {
        $chars = '!@#$%^&*()_+-=[]{}|;:\'",.<>?/~`';

        // HTML context
        $htmlEncoded = $this->service->encodeHtml($chars);
        $this->assertNotEmpty($htmlEncoded);

        // URL context
        $urlEncoded = $this->service->encodeUrl($chars);
        $this->assertNotEmpty($urlEncoded);

        // JS context
        $jsEncoded = $this->service->encodeJs($chars);
        $this->assertNotEmpty($jsEncoded);
    }
}
