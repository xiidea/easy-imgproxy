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
        $this->key = bin2hex(random_bytes(32));
        $this->salt = bin2hex(random_bytes(16));
        $this->baseUrl = 'http://localhost:8080';
    }

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

        $withoutProtocol = preg_replace('#https?://#', '', $url);
        $this->assertStringNotContainsString('//', $withoutProtocol);
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

        $this->assertStringEndsWith(self::base64url($imageUrl), $url);
    }

    public function testExtensionResolvedFromRelativePath(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);
        $imageUrl = '2117/amdad.png';

        $url = $builder->withImageUrl($imageUrl)->build();

        $this->assertStringEndsWith(self::base64url($imageUrl) . '.png', $url);
    }

    // =========================================================================
    // Signing correctness
    // =========================================================================

    public function testSignatureMatchesImgproxySpec(): void
    {
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

        // Short form: width → w
        $encodedUrl = self::base64url($imageUrl);
        $path = '/w:200/' . $encodedUrl . '.webp';
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
    // Processing options — short forms
    // =========================================================================

    public function testOptionsUseShortForm(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringNotContainsString('width', $url);
    }

    public function testMultipleOptionsAllUseShortForm(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withHeight(300)
            ->withQuality(80)
            ->withGravity('center')
            ->withResizing('fill')
            ->withExtension('png')
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
        $this->assertStringContainsString('/q:80/', $url);
        $this->assertStringContainsString('/g:center/', $url);
        $this->assertStringContainsString('/rt:fill/', $url);
        $this->assertStringEndsWith('.png', $url);
    }

    public function testShortFormInputPassedThrough(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // User passes short form directly
        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('w', 200)
            ->withOption('h', 300)
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
    }

    public function testUnknownOptionPassedThrough(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('custom_thing', 'value')
            ->build();

        // Unknown options are passed as-is
        $this->assertStringContainsString('/custom_thing:value/', $url);
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
        $this->assertStringNotContainsString('/w:', $url2);
    }

    public function testBuilderFluentInterfaceReturnsBuilderInstance(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $this->assertInstanceOf(UrlBuilder::class, $builder->withImageUrl('https://example.com/image.jpg'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withWidth(200));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withExtension('webp'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->withServerPreset('blur'));
        $this->assertInstanceOf(UrlBuilder::class, $builder->usePresetsOnly());
        $this->assertInstanceOf(UrlBuilder::class, $builder->enablePro());
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

        // Uses short form "pr:" for preset
        $this->assertStringContainsString('/pr:blurry/', $url);
    }

    public function testBuilderWithMultipleServerPresets(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPresets(['blur', 'sharpen'])
            ->build();

        $this->assertStringContainsString('/pr:blur:sharpen/', $url);
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

        $this->assertStringContainsString('/pr:blurry/', $url);
        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
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

        $this->assertStringContainsString('/pr:blurry/', $url1);
        $this->assertStringNotContainsString('pr:', $url2);
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

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:200/', $url);
        $this->assertStringContainsString('/rt:fill/', $url);
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

        $this->assertStringContainsString('/w:300/', $url);
        $this->assertStringContainsString('/h:200/', $url);
        $this->assertStringContainsString('/q:85/', $url);
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

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
        $this->assertStringContainsString('/q:80/', $url);
    }

    // =========================================================================
    // Pro options
    // =========================================================================

    public function testProOptionsIgnoredWhenProDisabled(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // Suppress the trigger_error warning for clean test output
        $url = @$builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withOption('brightness', 50)
            ->withOption('saturation', 30)
            ->build();

        // Free option present (short form)
        $this->assertStringContainsString('/w:200/', $url);
        // Pro options dropped
        $this->assertStringNotContainsString('br:', $url);
        $this->assertStringNotContainsString('sa:', $url);
        $this->assertStringNotContainsString('brightness', $url);
        $this->assertStringNotContainsString('saturation', $url);
    }

    public function testProOptionsIncludedWhenProEnabled(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, false, true);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withOption('brightness', 50)
            ->withOption('saturation', 30)
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/br:50/', $url);
        $this->assertStringContainsString('/sa:30/', $url);
    }

    public function testProOptionTriggersWarningWhenDisabled(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $warning = null;
        set_error_handler(function (int $errno, string $errstr) use (&$warning): bool {
            $warning = $errstr;
            return true;
        }, E_USER_WARNING);

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('brightness', 50)
            ->build();

        restore_error_handler();

        $this->assertNotNull($warning);
        $this->assertStringContainsString('brightness', $warning);
        $this->assertStringContainsString('enable_pro is false', $warning);
    }

    public function testMultipleProOptionsEachTriggerWarning(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $warnings = [];
        set_error_handler(function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        }, E_USER_WARNING);

        $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('brightness', 50)
            ->withOption('contrast', 20)
            ->withOption('width', 200)
            ->build();

        restore_error_handler();

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('brightness', $warnings[0]);
        $this->assertStringContainsString('contrast', $warnings[1]);
    }

    public function testEnableProTogglesViaCode(): void
    {
        // Default: pro disabled
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // Enable via code
        $builder->enablePro();

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('brightness', 50)
            ->build();

        $this->assertStringContainsString('/br:50/', $url);
    }

    public function testEnableProCanBeDisabledViaCode(): void
    {
        // Start with pro enabled
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, null, false, true);

        // Disable via code
        $builder->enablePro(false);

        $url = @$builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('brightness', 50)
            ->build();

        $this->assertStringNotContainsString('br:', $url);
    }

    public function testProShortFormAlsoDetectedAsPro(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        // Using short form directly
        $url = @$builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withOption('br', 50)
            ->build();

        // Short form 'br' maps to 'brightness' which is pro — should be dropped
        $this->assertStringNotContainsString('br:', $url);
    }

    public function testFreeOptionsNotAffectedByProFlag(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withWidth(200)
            ->withHeight(300)
            ->withQuality(80)
            ->withOption('blur', 5)
            ->withOption('sharpen', 2)
            ->build();

        // All free options present
        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringContainsString('/h:300/', $url);
        $this->assertStringContainsString('/q:80/', $url);
        $this->assertStringContainsString('/bl:5/', $url);
        $this->assertStringContainsString('/sh:2/', $url);
    }

    public function testProPresetOptionsIgnoredWhenProDisabled(): void
    {
        $registry = new PresetRegistry();
        $registry->register('fancy', new Preset(
            ['width' => 200, 'brightness' => 50, 'contrast' => 20],
            null
        ));

        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl, $registry);

        $url = @$builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withPreset('fancy')
            ->build();

        $this->assertStringContainsString('/w:200/', $url);
        $this->assertStringNotContainsString('br:', $url);
        $this->assertStringNotContainsString('co:', $url);
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

        $this->assertStringNotContainsString('pr:', $url);
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

        // Extension auto-resolved from .png
        $encodedUrl = self::base64url($imageUrl);
        $path = '/avatar/' . $encodedUrl . '.png';
        $binKey = hex2bin($key);
        $binSalt = hex2bin($salt);
        $expectedSig = self::base64url(hash_hmac('sha256', $binSalt . $path, $binKey, true));

        $expected = $this->baseUrl . '/' . $expectedSig . $path;
        $this->assertSame($expected, $url);
    }

    // =========================================================================
    // usePresetsOnly() / enablePro() toggles
    // =========================================================================

    public function testUsePresetsOnlyTogglesMode(): void
    {
        $builder = new UrlBuilder($this->key, $this->salt, $this->baseUrl);

        $url1 = $builder
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringContainsString('/pr:blurry/', $url1);

        $builder->reset();
        $url2 = $builder
            ->usePresetsOnly()
            ->withImageUrl('https://example.com/image.jpg')
            ->withServerPreset('blurry')
            ->build();

        $this->assertStringNotContainsString('pr:', $url2);
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

        $this->assertStringContainsString('/pr:blurry/', $url);
        $this->assertStringContainsString('/w:200/', $url);
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
