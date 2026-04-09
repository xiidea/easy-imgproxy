<?php

declare(strict_types=1);

namespace Xiidea\EasyImgProxyBundle\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Xiidea\EasyImgProxyBundle\Builder\UrlBuilder;
use Xiidea\EasyImgProxyBundle\Exception\InvalidUrlBuilderException;
use Xiidea\EasyImgProxyBundle\Preset\Preset;
use Xiidea\EasyImgProxyBundle\Preset\PresetRegistry;

class UrlBuilderTest extends TestCase
{
    private string $key;

    private string $salt;

    private string $baseUrl;

    protected function setUp(): void
    {
        // Test credentials (hex-encoded)
        $this->key = bin2hex(random_bytes(32));
        $this->salt = bin2hex(random_bytes(16));
        $this->baseUrl = 'http://localhost:8080';
    }

    public function testBuilderThrowsExceptionWhenImageUrlNotSet(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Image URL is required.');

        $builder->build();
    }

    public function testBuilderThrowsExceptionWhenImageUrlIsEmpty(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $builder->withImageUrl('');

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Image URL cannot be empty.');

        $builder->build();
    }

    public function testBuilderGeneratesUrlWithImageUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/image.jpg';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringStartsWith($this->baseUrl, $url);
        $this->assertStringContainsString(rawurlencode($imageUrl), $url);
    }

    public function testBuilderGeneratesUrlWithWidth(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
    }

    public function testBuilderGeneratesUrlWithHeightAndWidth(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withHeight(300)
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/300/', $url);
    }

    public function testBuilderGeneratesUrlWithExtension(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withExtension('webp')
            ->build();

        $this->assertStringContainsString('/format/webp/', $url);
    }

    public function testBuilderGeneratesUrlWithMultipleOptions(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withHeight(300)
            ->withQuality(80)
            ->withGravity('center')
            ->withExtension('png')
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/300/', $url);
        $this->assertStringContainsString('/quality/80/', $url);
        $this->assertStringContainsString('/gravity/center/', $url);
        $this->assertStringContainsString('/format/png/', $url);
    }

    public function testBuilderGeneratesConsistentSignature(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $builder->reset();

        $url2 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertSame($url1, $url2);
    }

    public function testBuilderResetsClearsPreviousOptions(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $builder->reset();

        $url2 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();

        $this->assertNotSame($url1, $url2);
        $this->assertStringNotContainsString('/width/200/', $url2);
    }

    public function testBuilderWithCustomOption(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('dpr', 2)
            ->withOption('background', 'ffffff')
            ->build();

        $this->assertStringContainsString('/dpr/2/', $url);
        $this->assertStringContainsString('/background/ffffff/', $url);
    }

    public function testBuilderWithResizing(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withResizing('fill')
            ->withWidth(200)
            ->withHeight(300)
            ->build();

        $this->assertStringContainsString('/resizing_type/fill/', $url);
    }

    public function testSignatureIsUrlSafe(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        // Extract signature (second part after base_url)
        $parts = explode('/', str_replace($this->baseUrl . '/', '', $url));
        $signature = $parts[0];

        // Verify signature contains only URL-safe characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $signature);
        // Verify no padding
        $this->assertStringNotContainsString('=', $signature);
    }

    public function testBuilderThrowsExceptionWithInvalidHexKey(): void
    {
        $builder = new UrlBuilder('invalid_hex_key', $this->salt, $this->baseUrl);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Invalid hex-encoded key or salt.');

        $builder->withImageUrl('https://example.com/image.jpg')->build();
    }

    public function testBuilderThrowsExceptionWithInvalidHexSalt(): void
    {
        $builder = new UrlBuilder($this->key, 'invalid_hex_salt', $this->baseUrl);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Invalid hex-encoded key or salt.');

        $builder->withImageUrl('https://example.com/image.jpg')->build();
    }

    public function testBuilderHandlesBaseUrlWithTrailingSlash(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl . '/');

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();

        // Should not have double slashes between base URL and signature
        $withoutProtocol = preg_replace('#https?://#', '', $url);
        $this->assertStringNotContainsString('//', $withoutProtocol);
    }

    public function testBuilderWithSpecialCharactersInImageUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/image with spaces & special.jpg';

        $url = $builder
            ->withImageUrl($imageUrl)
            ->build();

        $this->assertStringContainsString(rawurlencode($imageUrl), $url);
    }

    public function testBuilderFluentInterfaceReturnsBuilderInstance(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $result = $builder->withImageUrl('https://example.com/image.jpg');
        $this->assertInstanceOf(UrlBuilder::class, $result);

        $result = $builder->withWidth(200);
        $this->assertInstanceOf(UrlBuilder::class, $result);

        $result = $builder->withExtension('webp');
        $this->assertInstanceOf(UrlBuilder::class, $result);

        $result = $builder->reset();
        $this->assertInstanceOf(UrlBuilder::class, $result);
    }

    public function testBuilderWithServerPreset(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/preset/blurry/', $url);
    }

    public function testBuilderWithServerPresetWithParameters(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry:thumb')
            ->build();

        $this->assertStringContainsString('/preset/blurry:thumb/', $url);
    }

    public function testBuilderWithMultipleServerPresets(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blur', 'sharpen'])
            ->build();

        $this->assertStringContainsString('/preset/blur/', $url);
        $this->assertStringContainsString('/preset/sharpen/', $url);
    }

    public function testBuilderServerPresetWithOtherOptions(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withWidth(200)
            ->withHeight(300)
            ->withQuality(80)
            ->build();

        $this->assertStringContainsString('/preset/blurry/', $url);
        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/300/', $url);
        $this->assertStringContainsString('/quality/80/', $url);
    }

    public function testBuilderServerPresetOrderPreserved(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('filter1')
            ->withServerPreset('filter2')
            ->withServerPreset('filter3')
            ->build();

        // Extract path to verify preset order
        $parts = explode('/', str_replace($this->baseUrl . '/', '', $url));
        // Find preset indices
        $filter1Index = array_search('filter1', $parts);
        $filter2Index = array_search('filter2', $parts);
        $filter3Index = array_search('filter3', $parts);

        $this->assertLessThan($filter2Index, $filter1Index);
        $this->assertLessThan($filter3Index, $filter2Index);
    }

    public function testBuilderServerPresetDuplicatesNotAdded(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withServerPreset('blurry')
            ->build();

        // Should only contain one /preset/blurry/ segment
        $count = substr_count($url, '/preset/blurry/');
        $this->assertSame(1, $count);
    }

    public function testBuilderCustomPresetApplied(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumbnail', new Preset(
            ['width' => 200, 'height' => 200, 'resizing_type' => 'fill'],
            'webp'
        ));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('thumbnail')
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/200/', $url);
        $this->assertStringContainsString('/resizing_type/fill/', $url);
        $this->assertStringContainsString('/format/webp/', $url);
    }

    public function testBuilderCustomPresetOverriddenByExplicitOptions(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumbnail', new Preset(
            ['width' => 200, 'height' => 200, 'quality' => 70],
            null
        ));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('thumbnail')
            ->withWidth(300)
            ->withQuality(85)
            ->build();

        // Width and quality should use explicit values, not preset values
        $this->assertStringContainsString('/width/300/', $url);
        $this->assertStringContainsString('/height/200/', $url);  // Not overridden
        $this->assertStringContainsString('/quality/85/', $url);
    }

    public function testBuilderMultipleCustomPresets(): void
    {
        $registry = new PresetRegistry();
        $registry->register('dimensions', new Preset(['width' => 200, 'height' => 300], null));
        $registry->register('quality', new Preset(['quality' => 80], null));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withPresets(['dimensions', 'quality'])
            ->build();

        $this->assertStringContainsString('/width/200/', $url);
        $this->assertStringContainsString('/height/300/', $url);
        $this->assertStringContainsString('/quality/80/', $url);
    }

    public function testBuilderResetClearsServerPresets(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $builder->reset();

        $url2 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();

        $this->assertStringContainsString('/preset/blurry/', $url1);
        $this->assertStringNotContainsString('/preset/blurry/', $url2);
    }

    // =========================================================================
    // Presets-only mode tests
    // =========================================================================

    public function testPresetsOnlyGeneratesColonSeparatedUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        // In presets-only mode: /{sig}/{preset}/{url} — no /preset/ prefix
        $this->assertStringNotContainsString('/preset/', $url);
        $this->assertStringContainsString('/blurry/', $url);
    }

    public function testPresetsOnlyMultiplePresetsJoinedByColon(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blurry', 'thumb', 'quality_high'])
            ->build();

        $this->assertStringContainsString('/blurry:thumb:quality_high/', $url);
    }

    public function testPresetsOnlyRejectsProcessingOptions(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Processing options are not allowed in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withWidth(200)
            ->build();
    }

    public function testPresetsOnlyRejectsExtension(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Extension is not allowed in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withExtension('webp')
            ->build();
    }

    public function testPresetsOnlyRejectsCustomPresets(): void
    {
        $registry = new PresetRegistry();
        $registry->register('thumbnail', new Preset(['width' => 200], 'webp'));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry, true);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('Custom presets with options are not allowed in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withPreset('thumbnail')
            ->build();
    }

    public function testPresetsOnlyRequiresAtLeastOneServerPreset(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('At least one server preset is required in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();
    }

    public function testPresetsOnlyConsistentSignature(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blurry', 'thumb'])
            ->build();

        $builder->reset();

        $url2 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blurry', 'thumb'])
            ->build();

        $this->assertSame($url1, $url2);
    }

    public function testPresetsOnlyDifferentPresetOrderProducesDifferentUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blurry', 'thumb'])
            ->build();

        $builder->reset();

        $url2 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['thumb', 'blurry'])
            ->build();

        $this->assertNotSame($url1, $url2);
        $this->assertStringContainsString('/blurry:thumb/', $url1);
        $this->assertStringContainsString('/thumb:blurry/', $url2);
    }

    public function testUsePresetsOnlyTogglesMode(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // Standard mode by default
        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/preset/blurry/', $url1);

        // Switch to presets-only mode via code
        $builder->reset();
        $url2 = $builder
            ->usePresetsOnly()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringNotContainsString('/preset/', $url2);
        $this->assertStringContainsString('/blurry/', $url2);
    }

    public function testUsePresetsOnlyCanBeDisabled(): void
    {
        // Start in presets-only mode
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        // Disable via code
        $builder->usePresetsOnly(false);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withWidth(200)
            ->build();

        // Should work in standard mode now
        $this->assertStringContainsString('/preset/blurry/', $url);
        $this->assertStringContainsString('/width/200/', $url);
    }

    public function testPresetsOnlyModePreservedAfterReset(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $builder->reset();

        // Mode should still be presets-only after reset
        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('At least one server preset is required in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();
    }

    public function testPresetsOnlyUrlStructure(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $imageUrl = 'https://example.com/image.jpg';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPresets(['blur', 'sharp'])
            ->build();

        // Structure: {base}/{signature}/{preset1:preset2}/{encoded_url}
        $withoutBase = str_replace($this->baseUrl . '/', '', $url);
        $parts = explode('/', $withoutBase, 3);

        // parts[0] = signature, parts[1] = colon-separated presets, parts[2] = encoded URL
        $this->assertCount(3, $parts);
        $this->assertSame('blur:sharp', $parts[1]);
        $this->assertSame(rawurlencode($imageUrl), $parts[2]);
    }

    public function testPresetsOnlySignatureIsUrlSafe(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $withoutBase = str_replace($this->baseUrl . '/', '', $url);
        $signature = explode('/', $withoutBase)[0];

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $signature);
        $this->assertStringNotContainsString('=', $signature);
    }
}
