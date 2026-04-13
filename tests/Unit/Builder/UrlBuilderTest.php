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

    /**
     * Helper to encode a URL the same way the builder does.
     */
    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // =========================================================================
    // Validation
    // =========================================================================

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

    // =========================================================================
    // URL encoding & structure
    // =========================================================================

    public function testBuilderGeneratesUrlWithBase64EncodedImageUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/image.jpg';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringStartsWith($this->baseUrl, $url);
        $this->assertStringContainsString(self::base64url($imageUrl), $url);
    }

    public function testBuilderGeneratesUrlWithExtensionAsSuffix(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/image.jpg';
        $encodedUrl = self::base64url($imageUrl);

        $url = $builder
            ->withImageUrl($imageUrl)
            ->withExtension('webp')
            ->build();

        $this->assertStringEndsWith($encodedUrl . '.webp', $url);
    }

    public function testBuilderHandlesBaseUrlWithTrailingSlash(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl . '/');

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();

        // Should not have double slashes (ignoring protocol)
        $withoutProtocol = preg_replace('#https?://#', '', $url);
        $this->assertStringNotContainsString('//', $withoutProtocol);
    }

    // =========================================================================
    // Extension resolution (explicit > preset > URL path)
    // =========================================================================

    public function testExtensionAutoResolvedFromImageUrlPath(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/photo.png';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringEndsWith(self::base64url($imageUrl) . '.png', $url);
    }

    public function testExplicitExtensionOverridesUrlPath(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/photo.jpg')
            ->withExtension('webp')
            ->build();

        $this->assertStringEndsWith('.webp', $url);
        $this->assertStringNotContainsString('.jpg', $url);
    }

    public function testPresetExtensionOverridesUrlPath(): void
    {
        $registry = new PresetRegistry();
        $registry->register('webp_out', new Preset([], 'webp'));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = $builder
            ->withImageUrl('https://example.com/photo.jpg')
            ->withPreset('webp_out')
            ->build();

        $this->assertStringEndsWith('.webp', $url);
    }

    public function testExplicitExtensionOverridesPresetExtension(): void
    {
        $registry = new PresetRegistry();
        $registry->register('jpg_out', new Preset([], 'jpg'));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = $builder
            ->withImageUrl('https://example.com/photo.jpg')
            ->withPreset('jpg_out')
            ->withExtension('avif')
            ->build();

        $this->assertStringEndsWith('.avif', $url);
    }

    public function testNoExtensionWhenUrlHasNone(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = 'https://example.com/image';

        $url = $builder->withImageUrl($imageUrl)->build();

        // Ends with the encoded URL, no dot suffix
        $this->assertStringEndsWith(self::base64url($imageUrl), $url);
    }

    public function testNoExtensionForNonUrlPath(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        // Relative path without extension (e.g., S3 key)
        $imageUrl = '2117/amdad';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringEndsWith(self::base64url($imageUrl), $url);
    }

    public function testExtensionResolvedFromRelativePath(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = '2117/amdad.png';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringEndsWith(self::base64url($imageUrl) . '.png', $url);
    }

    public function testSignatureIsUrlSafe(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $parts = explode('/', str_replace($this->baseUrl . '/', '', $url));
        $signature = $parts[0];

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $signature);
        $this->assertStringNotContainsString('=', $signature);
    }

    // =========================================================================
    // Signing correctness
    // =========================================================================

    public function testSignatureMatchesImgproxySpec(): void
    {
        // Use known key/salt for deterministic test
        $key = 'aabbccdd' . str_repeat('00', 28);
        $salt = 'eeff0011' . str_repeat('00', 12);
        $builder = new UrlBuilder($key, $salt, $this->baseUrl);
        $imageUrl = 'https://example.com/test.jpg';

        $url = $builder->withImageUrl($imageUrl)->build();

        // Extension auto-resolved from URL path (.jpg)
        $encodedUrl = self::base64url($imageUrl);
        $path = '/' . $encodedUrl . '.jpg';
        $binKey = hex2bin($key);
        $binSalt = hex2bin($salt);
        $expectedSig = self::base64url(hash_hmac('sha256', $binSalt . $path, $binKey, true));

        $expected = $this->baseUrl . '/' . $expectedSig . $path;
        $this->assertSame($expected, $url);
    }

    public function testSignatureMatchesImgproxySpecWithOptions(): void
    {
        $key = 'aabbccdd' . str_repeat('00', 28);
        $salt = 'eeff0011' . str_repeat('00', 12);
        $builder = new UrlBuilder($key, $salt, $this->baseUrl);
        $imageUrl = 'https://example.com/test.jpg';

        $url = $builder
            ->withImageUrl($imageUrl)
            ->withWidth(200)
            ->withExtension('webp')
            ->build();

        // Manually compute expected
        $encodedUrl = self::base64url($imageUrl);
        $path = '/width:200/' . $encodedUrl . '.webp';
        $binKey = hex2bin($key);
        $binSalt = hex2bin($salt);
        $expectedSig = self::base64url(hash_hmac('sha256', $binSalt . $path, $binKey, true));

        $expected = $this->baseUrl . '/' . $expectedSig . $path;
        $this->assertSame($expected, $url);
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

    // =========================================================================
    // Processing options (standard mode)
    // =========================================================================

    public function testBuilderGeneratesOptionInKeyColonValueFormat(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/width:200/', $url);
    }

    public function testBuilderGeneratesMultipleOptions(): void
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

        $this->assertStringContainsString('/width:200/', $url);
        $this->assertStringContainsString('/height:300/', $url);
        $this->assertStringContainsString('/quality:80/', $url);
        $this->assertStringContainsString('/gravity:center/', $url);
        $this->assertStringEndsWith('.png', $url);
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

        $this->assertStringContainsString('/resizing_type:fill/', $url);
    }

    public function testBuilderWithCustomOption(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('dpr', 2)
            ->withOption('background', 'ffffff')
            ->build();

        $this->assertStringContainsString('/dpr:2/', $url);
        $this->assertStringContainsString('/background:ffffff/', $url);
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
        $this->assertStringNotContainsString('width', $url2);
    }

    public function testBuilderFluentInterfaceReturnsBuilderInstance(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $this->assertInstanceOf(UrlBuilder::class, $builder->withImageUrl('https://example.com/image.jpg'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withWidth(200));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withExtension('webp'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withServerPreset('blur'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->usePresetsOnly());
        $this->assertInstanceOf(UrlBuilder::class, $builder->reset());
    }

    // =========================================================================
    // Server presets (standard mode)
    // =========================================================================

    public function testBuilderWithServerPreset(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/preset:blurry/', $url);
    }

    public function testBuilderWithMultipleServerPresets(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blur', 'sharpen'])
            ->build();

        // Multiple presets in a single segment: preset:blur:sharpen
        $this->assertStringContainsString('/preset:blur:sharpen/', $url);
    }

    public function testBuilderServerPresetWithOtherOptions(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withWidth(200)
            ->withHeight(300)
            ->build();

        $this->assertStringContainsString('/preset:blurry/', $url);
        $this->assertStringContainsString('/width:200/', $url);
        $this->assertStringContainsString('/height:300/', $url);
    }

    public function testBuilderServerPresetDuplicatesNotAdded(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withServerPreset('blurry')
            ->build();

        $count = substr_count($url, 'blurry');
        $this->assertSame(1, $count);
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

        $this->assertStringContainsString('/preset:blurry/', $url1);
        $this->assertStringNotContainsString('preset', $url2);
    }

    // =========================================================================
    // Custom presets (from registry)
    // =========================================================================

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

        $this->assertStringContainsString('/width:200/', $url);
        $this->assertStringContainsString('/height:200/', $url);
        $this->assertStringContainsString('/resizing_type:fill/', $url);
        $this->assertStringEndsWith('.webp', $url);
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

        $this->assertStringContainsString('/width:300/', $url);
        $this->assertStringContainsString('/height:200/', $url);
        $this->assertStringContainsString('/quality:85/', $url);
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

        $this->assertStringContainsString('/width:200/', $url);
        $this->assertStringContainsString('/height:300/', $url);
        $this->assertStringContainsString('/quality:80/', $url);
    }

    // =========================================================================
    // Presets-only mode
    // =========================================================================

    public function testPresetsOnlyGeneratesCorrectUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        // No "preset:" prefix in presets-only mode
        $this->assertStringNotContainsString('preset:', $url);
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

    public function testPresetsOnlyUrlStructure(): void
    {
        $key = 'aabbccdd' . str_repeat('00', 28);
        $salt = 'eeff0011' . str_repeat('00', 12);
        $builder = new UrlBuilder($key, $salt, $this->baseUrl, null, true);

        $imageUrl = '2117/amdad.png';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPresets(['blur', 'sharp'])
            ->build();

        // Structure: {base}/{signature}/{preset1:preset2}/{base64url(imageUrl)}.{ext}
        $withoutBase = str_replace($this->baseUrl . '/', '', $url);
        $parts = explode('/', $withoutBase, 3);

        $this->assertCount(3, $parts);
        $this->assertSame('blur:sharp', $parts[1]);
        $this->assertSame(self::base64url($imageUrl) . '.png', $parts[2]);
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

    public function testPresetsOnlyAutoResolvesExtensionFromUrl(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $imageUrl = 'https://example.com/image.jpg';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPreset('avatar')
            ->build();

        $this->assertStringEndsWith(self::base64url($imageUrl) . '.jpg', $url);
    }

    public function testPresetsOnlyWithExplicitExtension(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $imageUrl = '2117/amdad.png';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPreset('avatar')
            ->withExtension('webp')
            ->build();

        $this->assertStringEndsWith('.webp', $url);
    }

    public function testPresetsOnlyNoExtensionWhenUrlHasNone(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $imageUrl = '2117/amdad';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPreset('avatar')
            ->build();

        $this->assertStringEndsWith(self::base64url($imageUrl), $url);
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

    public function testPresetsOnlyDifferentOrderProducesDifferentUrl(): void
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

    public function testPresetsOnlySignatureMatchesSpec(): void
    {
        $key = 'aabbccdd' . str_repeat('00', 28);
        $salt = 'eeff0011' . str_repeat('00', 12);
        $builder = new UrlBuilder($key, $salt, $this->baseUrl, null, true);

        $imageUrl = '2117/amdad.png';
        $url = $builder
            ->withImageUrl($imageUrl)
            ->withServerPreset('avatar')
            ->build();

        // Manually compute expected — extension auto-resolved from .png
        $encodedUrl = self::base64url($imageUrl);
        $path = '/avatar/' . $encodedUrl . '.png';
        $binKey = hex2bin($key);
        $binSalt = hex2bin($salt);
        $expectedSig = self::base64url(hash_hmac('sha256', $binSalt . $path, $binKey, true));

        $expected = $this->baseUrl . '/' . $expectedSig . $path;
        $this->assertSame($expected, $url);
    }

    // =========================================================================
    // usePresetsOnly() toggle
    // =========================================================================

    public function testUsePresetsOnlyTogglesMode(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // Standard mode by default
        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/preset:blurry/', $url1);

        // Switch to presets-only
        $builder->reset();
        $url2 = $builder
            ->usePresetsOnly()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringNotContainsString('preset:', $url2);
        $this->assertStringContainsString('/blurry/', $url2);
    }

    public function testUsePresetsOnlyCanBeDisabled(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $builder->usePresetsOnly(false);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/preset:blurry/', $url);
        $this->assertStringContainsString('/width:200/', $url);
    }

    public function testPresetsOnlyModePreservedAfterReset(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, true);

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $builder->reset();

        $this->expectException(InvalidUrlBuilderException::class);
        $this->expectExceptionMessage('At least one server preset is required in presets-only mode.');

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->build();
    }
}
